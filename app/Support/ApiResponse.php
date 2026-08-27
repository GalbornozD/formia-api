<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Sobre estándar de toda respuesta JSON de la API: {status, code, data, meta}
 * para éxito; {status, code, message, errors?, error_code?, data:null, meta}
 * para error. `code` siempre refleja el status HTTP real de la respuesta —
 * nunca un código de negocio aparte, para eso está `error_code` (ej.
 * "empresa_sin_acceso"). `meta.request_id` es la pieza de trazabilidad: la
 * asigna AssignRequestId por request y queda también en los logs del
 * servidor (Log::shareContext), para poder correlacionar un reporte del
 * cliente con los logs sin exponer detalles internos en el body — ese es
 * el ángulo de seguridad: el mensaje de error que ve el cliente puede (y en
 * producción debe) ser genérico, pero el request_id permite investigar el
 * caso puntual del lado del servidor.
 */
final class ApiResponse
{
    public static function success(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'code' => $status,
            'data' => $data,
            'meta' => self::meta(),
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function error(
        string $message,
        int $status,
        array $errors = [],
        ?string $errorCode = null,
    ): JsonResponse {
        $body = [
            'status' => 'error',
            'code' => $status,
            'message' => $message,
            'data' => null,
            'meta' => self::meta(),
        ];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        if ($errorCode !== null) {
            $body['error_code'] = $errorCode;
        }

        return response()->json($body, $status);
    }

    /**
     * Reempaqueta una respuesta de error que Laravel ya armó solo (401 de
     * auth, 403 de autorización, 404, 422 de validación, 429 de throttle,
     * 500...) en el mismo sobre — sin reimplementar esa lógica de mapeo acá,
     * solo tomamos el status/body que el Handler default ya resolvió.
     */
    public static function fromRenderedResponse(Response $response, Throwable $e, Request $request): JsonResponse
    {
        $status = $response->getStatusCode();
        $original = json_decode($response->getContent() ?: '{}', true);
        $original = is_array($original) ? $original : [];

        $message = $original['message'] ?? ($status >= 500 ? 'Ha ocurrido un error inesperado.' : 'Error.');
        $errors = is_array($original['errors'] ?? null) ? $original['errors'] : [];

        // Nuestras propias respuestas ad-hoc (ej. ResolveEmpresaActiva antes
        // de este cambio) usaban 'code' en la raíz para un código de negocio;
        // ya no debería llegar así, pero se preserva por compatibilidad.
        $errorCode = $original['error_code'] ?? $original['code'] ?? null;

        return self::error($message, $status, $errors, is_string($errorCode) ? $errorCode : null);
    }

    /**
     * @return array{request_id: string, timestamp: string}
     */
    private static function meta(): array
    {
        return [
            'request_id' => request()->attributes->get('request_id') ?? (string) Str::uuid(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
