<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Catálogo de roles que el usuario autenticado puede otorgar (ver
     * Role::asignablesPor) — única fuente para el combo de rol en el alta/
     * edición de usuarios, tanto acá como en la validación del backend.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            Role::asignablesPor($request->user())->get(['id', 'name', 'description']),
        );
    }
}
