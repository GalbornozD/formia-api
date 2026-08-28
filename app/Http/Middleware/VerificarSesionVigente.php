<?php

namespace App\Http\Middleware;

use App\Models\Session;
use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La cookie de sesión de Laravel puede seguir siendo técnicamente válida
 * aunque el usuario haya cerrado ESE dispositivo desde otro (ver
 * SessionController::destroy) — acá es donde se corta en el siguiente
 * request. Sesiones sin `AuthService::CLAVE_SESION_ID` (anteriores a este
 * cambio) pasan sin chequeo.
 *
 * `expires_at` es una ventana deslizante, igual que la cookie de sesión de
 * Laravel (que Illuminate\Session\Middleware\StartSession recalcula como
 * "ahora + session.lifetime" en cada response): acá se renueva en cada
 * request que pasa el chequeo, para que esta fila de auditoría no expire
 * sola mientras el usuario sigue activo.
 */
class VerificarSesionVigente
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sesionId = $request->session()->get(AuthService::CLAVE_SESION_ID);

        if ($sesionId === null) {
            return $next($request);
        }

        $sesion = Session::find($sesionId);

        if ($sesion === null || ! $sesion->estaVigente()) {
            $request->session()->invalidate();

            abort(401, 'Esta sesión fue cerrada desde otro dispositivo.');
        }

        $sesion->update(['expires_at' => now()->addMinutes((int) config('session.lifetime'))]);

        return $next($request);
    }
}
