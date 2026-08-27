<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un id único por request para trazabilidad: se expone en el header de
 * respuesta y en meta.request_id (ver ApiResponse), y se comparte con el
 * logger (Log::shareContext) para que cada línea de log de este request lo
 * incluya — permite correlacionar un reporte del cliente con los logs del
 * servidor sin tener que exponerle detalles internos en la respuesta.
 *
 * Va primero en el stack (ver bootstrap/app.php) para que exista incluso si
 * algo más adelante (auth, empresa.activa, el controller) revienta.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        Log::shareContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
