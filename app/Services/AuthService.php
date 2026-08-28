<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\Session;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Clave en la sesión de Laravel que apunta a la fila `sessions` (registro
     * auditable de dispositivo) de este login — la usan también
     * VerificarSesionVigente y SessionController para saber "cuál soy yo".
     */
    public const CLAVE_SESION_ID = 'sesion_actual_id';

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
        $this->registrarSesion($usuario, $request);

        $this->auditLogger->registrar(AuditAction::Login, $usuario);

        return $usuario;
    }

    public function logout(Request $request): void
    {
        $usuario = $request->user();

        $this->sesionActual($request)?->update(['revoked_at' => now()]);

        Auth::guard('web')->logout();
        $request->session()->forget('empresa_activa_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($usuario !== null) {
            $this->auditLogger->registrar(AuditAction::Logout, $usuario);
        }
    }

    /**
     * Fila `sessions` (registro de dispositivo) asociada a esta sesión de
     * Laravel, si la tiene — sesiones creadas antes de este cambio no
     * tendrán `CLAVE_SESION_ID` y devuelven null.
     */
    public function sesionActual(Request $request): ?Session
    {
        $sesionId = $request->session()->get(self::CLAVE_SESION_ID);

        return $sesionId !== null ? Session::find($sesionId) : null;
    }

    /**
     * Registro auditable de "qué dispositivo, desde cuándo" (Ley 21.719,
     * derecho a saber) — separado del store de sesión de Laravel en sí.
     * `refresh_token_hash` no protege nada propio: es solo un identificador
     * único para la fila, sin uso hoy fuera de esta tabla.
     */
    private function registrarSesion(User $usuario, Request $request): void
    {
        $sesion = Session::create([
            'user_id' => $usuario->id,
            'refresh_token_hash' => hash('sha256', random_bytes(32)),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'ip_address' => $request->ip(),
            'expires_at' => now()->addMinutes((int) config('session.lifetime')),
        ]);

        $request->session()->put(self::CLAVE_SESION_ID, $sesion->id);
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
