<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmpresaUsuarioController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:password-reset');

Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:password-reset');

Route::middleware(['auth:sanctum', 'sesion.vigente'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Establecen la empresa activa en sesión — por eso van SIN el middleware
    // empresa.activa (ese exige que la empresa ya esté resuelta).
    Route::post('/seleccionar-empresa', [AuthController::class, 'seleccionarEmpresa']);
    Route::post('/cambiar-empresa', [AuthController::class, 'seleccionarEmpresa']);

    // Catálogo de roles asignables por el usuario autenticado (combo de
    // rol en el alta/edición de usuarios) — no depende de empresa activa,
    // el rol es global.
    Route::get('/roles', [RoleController::class, 'index']);

    // Autogestión de dispositivos/sesiones — alcance por usuario, no por
    // empresa activa, por eso van fuera del grupo empresa.activa.
    Route::get('/sesiones', [SessionController::class, 'index']);
    Route::get('/sesiones/todas', [SessionController::class, 'todas']);
    Route::delete('/sesiones/{sesion}', [SessionController::class, 'destroy']);

    // Rutas de negocio: exigen que ya haya una empresa activa válida antes de
    // llegar al controlador (master queda exento, ver ResolveEmpresaActiva).
    Route::middleware('empresa.activa')->group(function () {
        Route::apiResource('empresas.usuarios', EmpresaUsuarioController::class)
            ->parameters(['empresas' => 'empresa', 'usuarios' => 'usuario'])
            ->only(['index', 'store', 'update', 'destroy']);
    });
});
