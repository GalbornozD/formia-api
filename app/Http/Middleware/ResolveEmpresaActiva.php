<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\ApiResponse;
use App\Support\EmpresaContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En cada request de negocio valida que el usuario autenticado tenga una
 * fila `activa` en company_user para la empresa del contexto (header
 * X-Empresa-Id, con la sesión como respaldo) y la inyecta en EmpresaContext.
 *
 * Un usuario `master` (rol global) queda exento: no necesita membresía ni
 * empresa activa para pasar — ve/gestiona todas las empresas. Si igual
 * manda X-Empresa-Id, se resuelve esa empresa como contexto (útil para
 * crear registros "dentro de" una empresa puntual), sin exigir membresía.
 *
 * Defensa en profundidad para el resto de los roles: el frontend puede
 * mandar cualquier X-Empresa-Id, pero aquí es donde se valida contra la
 * membresía real — el filtro que importa vive en el backend, nunca en lo
 * que declara el cliente.
 */
class ResolveEmpresaActiva
{
    public function __construct(private readonly EmpresaContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario === null) {
            abort(401, 'No autenticado.');
        }

        $empresaId = $this->empresaSolicitada($request);

        if ($usuario->esMaster()) {
            if ($empresaId !== null) {
                $empresa = Company::find($empresaId);

                if ($empresa === null) {
                    return ApiResponse::error('La empresa indicada no existe.', 404, errorCode: 'empresa_no_existe');
                }

                $this->context->set($empresa);
                $request->session()->put('empresa_activa_id', $empresaId);
            }

            $this->context->setMaster();

            return $next($request);
        }

        if ($empresaId === null) {
            return ApiResponse::error('No hay empresa activa seleccionada.', 409, errorCode: 'empresa_no_seleccionada');
        }

        $membresia = $usuario->membresiaActivaEn($empresaId);

        if ($membresia === null) {
            return ApiResponse::error('No tienes acceso activo a esta empresa.', 403, errorCode: 'empresa_sin_acceso');
        }

        $this->context->set($membresia->empresa, $membresia);

        // Mantiene la sesión al día como respaldo cuando el cliente no
        // manda X-Empresa-Id (ej. primer request tras seleccionar-empresa).
        $request->session()->put('empresa_activa_id', $empresaId);

        return $next($request);
    }

    private function empresaSolicitada(Request $request): ?int
    {
        $header = $request->header('X-Empresa-Id');

        if ($header !== null && ctype_digit((string) $header)) {
            return (int) $header;
        }

        $sesion = $request->session()->get('empresa_activa_id');

        return is_numeric($sesion) ? (int) $sesion : null;
    }
}
