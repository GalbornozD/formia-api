<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Intentos fallidos consecutivos a partir de los cuales se empieza a
     * bloquear la cuenta (antes de eso, solo cuenta el rate limiter).
     */
    private const UMBRAL_BLOQUEO = 5;

    private const BLOQUEO_MAX_MINUTOS = 30;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function login(string $email, string $password, Request $request): User
    {
        $usuario = User::where('email', $email)->first();

        if ($usuario !== null && $usuario->estaBloqueado()) {
            $this->auditLogger->registrar(AuditAction::LoginFallido, $usuario, detalle: ['motivo' => 'cuenta_bloqueada']);

            throw ValidationException::withMessages([
                'email' => __('Cuenta bloqueada temporalmente. Intenta nuevamente en :minutos minuto(s).', [
                    'minutos' => (string) (now()->diffInMinutes($usuario->locked_until) + 1),
                ]),
            ]);
        }

        if ($usuario === null || ! Hash::check($password, $usuario->getAuthPassword())) {
            if ($usuario !== null) {
                $this->registrarIntentoFallido($usuario);
            }

            $this->auditLogger->registrar(AuditAction::LoginFallido, $usuario, detalle: ['email' => $email]);

            throw ValidationException::withMessages([
                'email' => __('Las credenciales no son válidas.'),
            ]);
        }

        if ($usuario->status !== true) {
            $this->auditLogger->registrar(AuditAction::LoginFallido, $usuario, detalle: ['motivo' => 'estado_no_activo']);

            throw ValidationException::withMessages([
                'email' => __('Tu cuenta no está activa.'),
            ]);
        }

        $usuario->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        Auth::guard('web')->login($usuario);
        $request->session()->regenerate();

        $this->auditLogger->registrar(AuditAction::Login, $usuario);

        return $usuario;
    }

    public function logout(Request $request): void
    {
        $usuario = $request->user();

        Auth::guard('web')->logout();
        $request->session()->forget('empresa_activa_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($usuario !== null) {
            $this->auditLogger->registrar(AuditAction::Logout, $usuario);
        }
    }

    private function registrarIntentoFallido(User $usuario): void
    {
        $intentos = $usuario->failed_login_attempts + 1;
        $bloqueadoHasta = $usuario->locked_until;

        if ($intentos >= self::UMBRAL_BLOQUEO) {
            $minutos = min(2 ** ($intentos - self::UMBRAL_BLOQUEO), self::BLOQUEO_MAX_MINUTOS);
            $bloqueadoHasta = now()->addMinutes($minutos);
        }

        $usuario->forceFill([
            'failed_login_attempts' => $intentos,
            'locked_until' => $bloqueadoHasta,
        ])->save();
    }
}
