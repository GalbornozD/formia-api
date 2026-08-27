<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\ResolveEmpresaActiva;
use App\Support\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Primero que nada: todo lo demás (auth, empresa.activa, el propio
        // controller) puede fallar, y el sobre de error igual necesita un
        // request_id (ver AssignRequestId).
        $middleware->prepend(AssignRequestId::class);

        // Sanctum SPA: cookie de sesión httpOnly + CSRF en vez de bearer tokens
        // (evita exponer credenciales de larga duración en el storage del navegador).
        $middleware->statefulApi();

        $middleware->alias([
            'empresa.activa' => ResolveEmpresaActiva::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Reempaqueta CUALQUIER respuesta de error de la API (401, 403, 404,
        // 422, 429, 500...) en el mismo sobre {status,code,data,meta} que
        // ApiResponse::success() — un solo lugar, sin tocar cada excepción
        // que Laravel ya sabe manejar sola.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return $response;
            }

            return ApiResponse::fromRenderedResponse($response, $e, $request);
        });
    })->create();
