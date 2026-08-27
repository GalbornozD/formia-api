<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    private const EXPIRA_MINUTOS = 30;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function solicitar(string $email): void
    {
        $usuario = User::where('email', $email)->first();

        // Respuesta idéntica exista o no la cuenta: evita que el endpoint
        // sirva para enumerar emails registrados.
        if ($usuario === null) {
            return;
        }

        $tokenPlano = Str::random(64);

        PasswordResetToken::create([
            'user_id' => $usuario->id,
            'token_hash' => hash('sha256', $tokenPlano),
            'expires_at' => now()->addMinutes(self::EXPIRA_MINUTOS),
        ]);

        $usuario->notify(new ResetPasswordNotification($tokenPlano));
    }

    public function restablecer(string $email, string $tokenPlano, string $passwordNueva): void
    {
        $usuario = User::where('email', $email)->first();

        $mensajeInvalido = ValidationException::withMessages([
            'token' => __('El enlace de restablecimiento no es válido o expiró.'),
        ]);

        if ($usuario === null) {
            throw $mensajeInvalido;
        }

        $registro = $usuario->passwordResetTokens()
            ->where('token_hash', hash('sha256', $tokenPlano))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($registro === null) {
            throw $mensajeInvalido;
        }

        $usuario->forceFill([
            'password_hash' => $passwordNueva,
            'password_updated_at' => now(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        $registro->forceFill(['used_at' => now()])->save();

        $this->auditLogger->registrar(AuditAction::CambioPassword, $usuario);
    }
}
