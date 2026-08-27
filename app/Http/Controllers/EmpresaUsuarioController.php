<?php

namespace App\Http\Controllers;

use App\Http\Requests\Usuario\StoreUsuarioRequest;
use App\Http\Requests\Usuario\UpdateUsuarioRequest;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Role;
use App\Models\User;
use App\Services\UsuarioService;
use App\Support\ApiResponse;
use App\Support\Permisos;
use Illuminate\Http\JsonResponse;

/**
 * CRUD de usuarios dentro de una empresa (company_user + el User asociado).
 * Autorización vía CompanyUserPolicy: master gestiona cualquier empresa,
 * administrador solo la suya (y nunca a un usuario master).
 */
class EmpresaUsuarioController extends Controller
{
    /** Columnas de company_user que de verdad se exponen — evita el SELECT * de la membresía. */
    private const COLUMNAS_COMPANY_USER = ['user_id', 'company_id', 'permission', 'status'];

    /** Columnas del User relacionado, vía eager load — mismo criterio. */
    private const COLUMNAS_USUARIO = 'usuario:id,uuid,email,first_name,last_name,status,role_id';

    public function __construct(private readonly UsuarioService $usuarioService) {}

    public function index(Company $empresa): JsonResponse
    {
        $this->authorize('viewAny', [CompanyUser::class, $empresa]);

        $usuarios = CompanyUser::with([self::COLUMNAS_USUARIO])
            ->where('company_id', $empresa->id)
            ->get(self::COLUMNAS_COMPANY_USER);

        return ApiResponse::success(
            $usuarios->map(fn (CompanyUser $companyUser) => $this->usuarioArray($companyUser)),
        );
    }

    public function store(StoreUsuarioRequest $request, Company $empresa): JsonResponse
    {
        $this->authorize('create', [CompanyUser::class, $empresa]);

        $companyUser = $this->usuarioService->crear($empresa, [
            'email' => $request->validated('email'),
            'first_name' => $request->validated('nombre'),
            'last_name' => $request->validated('apellido'),
            'password' => $request->validated('password'),
            'role_id' => $request->roleId(),
            'permission' => $request->permissionBitmask(),
        ], $request->user());

        return ApiResponse::success($this->usuarioArray($companyUser), 201);
    }

    public function update(UpdateUsuarioRequest $request, Company $empresa, User $usuario): JsonResponse
    {
        $companyUser = $this->companyUserOrFail($empresa, $usuario);

        $this->authorize('update', $companyUser);

        $companyUser = $this->usuarioService->actualizar($companyUser, $request->datosParaServicio());

        return ApiResponse::success($this->usuarioArray($companyUser));
    }

    public function destroy(Company $empresa, User $usuario): JsonResponse
    {
        $companyUser = $this->companyUserOrFail($empresa, $usuario);

        $this->authorize('delete', $companyUser);

        $this->usuarioService->eliminar($companyUser);

        return ApiResponse::success();
    }

    private function companyUserOrFail(Company $empresa, User $usuario): CompanyUser
    {
        $companyUser = CompanyUser::with([self::COLUMNAS_USUARIO])
            ->where('company_id', $empresa->id)
            ->where('user_id', $usuario->id)
            ->firstOrFail(self::COLUMNAS_COMPANY_USER);

        // Pivot::save()/delete() solo saben scopear su query por
        // (foreignKey, relatedKey) cuando el modelo se cargó a través de la
        // relación BelongsToMany; al traerlo con una query directa hay que
        // registrar esas dos columnas a mano, si no terminan operando sin
        // WHERE (afectando cualquier fila) porque este pivot no tiene `id`.
        return $companyUser->setPivotKeys('user_id', 'company_id');
    }

    /**
     * Mismos nombres que las columnas reales — `membership_status` porque
     * users.status y company_user.status chocarían en el mismo objeto.
     *
     * @return array<string, mixed>
     */
    private function usuarioArray(CompanyUser $companyUser): array
    {
        return [
            'user_id' => $companyUser->user_id,
            'company_id' => $companyUser->company_id,
            'uuid' => $companyUser->usuario->uuid,
            'email' => $companyUser->usuario->email,
            'first_name' => $companyUser->usuario->first_name,
            'last_name' => $companyUser->usuario->last_name,
            'status' => $companyUser->usuario->status,
            'role_id' => $companyUser->usuario->role_id,
            'role_name' => Role::nombreDe($companyUser->usuario->role_id),
            'membership_status' => (bool) $companyUser->status,
            'permissions' => Permisos::clavesDesdeBitmask($companyUser->permission),
        ];
    }
}
