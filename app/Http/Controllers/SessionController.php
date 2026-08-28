<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\Session;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Autogestión de "mis sesiones/dispositivos" (Ley 21.719, derecho a saber):
 * cada usuario ve y cierra únicamente sus propias sesiones vigentes. Además,
 * `todas()` da a master una vista de auditoría de TODAS las sesiones (ver
 * SessionPolicy::viewAny) — solo lectura, no puede cerrar sesiones ajenas.
 */
class SessionController extends Controller
{
    private const POR_PAGINA_DEFECTO = 15;

    private const POR_PAGINA_MAXIMO = 100;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $sesionActualId = $request->session()->get(AuthService::CLAVE_SESION_ID);

        $sesiones = $usuario->sesiones()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Session $sesion) => $this->sesionArray($sesion, $sesion->id === $sesionActualId));

        return ApiResponse::success($sesiones);
    }

    /**
     * Vista de auditoría para master: todas las sesiones de todos los
     * usuarios, paginadas y opcionalmente filtradas por `q` (email o
     * nombre del dueño de la sesión) — sin eso, con muchos usuarios activos
     * esto crece indefinidamente.
     */
    public function todas(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Session::class);

        $porPagina = min((int) $request->integer('per_page', self::POR_PAGINA_DEFECTO), self::POR_PAGINA_MAXIMO);
        $busqueda = $request->string('q')->trim()->toString();

        $sesiones = Session::query()
            ->with('usuario:id,uuid,email,first_name,last_name')
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->whereHas('usuario', function ($query) use ($busqueda) {
                    $query->where('email', 'like', "%{$busqueda}%")
                        ->orWhere('first_name', 'like', "%{$busqueda}%")
                        ->orWhere('last_name', 'like', "%{$busqueda}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($porPagina)
            ->through(fn (Session $sesion) => $this->sesionConUsuarioArray($sesion));

        return ApiResponse::success($sesiones);
    }

    public function destroy(Request $request, Session $sesion): JsonResponse
    {
        $this->authorize('delete', $sesion);

        $esSesionActual = $sesion->id === $request->session()->get(AuthService::CLAVE_SESION_ID);

        $sesion->update(['revoked_at' => now()]);

        $this->auditLogger->registrar(
            AuditAction::SesionRevocada,
            $request->user(),
            entidad: 'sessions',
            entidadId: (string) $sesion->id,
        );

        // Si es la sesión con la que se hizo este mismo request, se corta ya
        // mismo (mismo efecto que /logout); si es de otro dispositivo, ese
        // dispositivo queda cortado recién en su próximo request — ver
        // VerificarSesionVigente.
        if ($esSesionActual) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return ApiResponse::success(['message' => 'Sesión cerrada.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function sesionArray(Session $sesion, bool $esActual): array
    {
        return [
            'id' => $sesion->id,
            'user_id' => $sesion->user_id,
            'company_id' => $sesion->company_id,
            'user_agent' => $sesion->user_agent,
            'ip_address' => $sesion->ip_address,
            'created_at' => $sesion->created_at,
            'expires_at' => $sesion->expires_at,
            'revoked_at' => $sesion->revoked_at,
            'es_actual' => $esActual,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sesionConUsuarioArray(Session $sesion): array
    {
        return [
            ...$this->sesionArray($sesion, esActual: false),
            'usuario' => [
                'id' => $sesion->usuario->id,
                'email' => $sesion->usuario->email,
                'first_name' => $sesion->usuario->first_name,
                'last_name' => $sesion->usuario->last_name,
            ],
        ];
    }
}
