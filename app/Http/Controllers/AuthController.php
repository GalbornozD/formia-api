<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SeleccionarEmpresaRequest;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Services\PasswordResetService;
use App\Support\ApiResponse;
use App\Support\Permisos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /** Columnas de companies que de verdad se exponen — evita el SELECT * en cada login/me. */
    private const COLUMNAS_EMPRESA = [
        'companies.id',
        'companies.uuid',
        'companies.legal_name',
        'companies.rut',
        'companies.status',
    ];

    public function __construct(
        private readonly AuthService $authService,
        private readonly PasswordResetService $passwordResetService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $usuario = $this->authService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request,
        );

        return $this->respuestaConEmpresas($request, $usuario);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request);

        return ApiResponse::success(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $this->respuestaConEmpresas($request, $usuario);
    }

    /**
     * Fija la empresa activa en la sesión. Sirve tanto para /seleccionar-empresa
     * (justo tras el login) como para /cambiar-empresa (sesión ya en curso):
     * la regla — membresía activa del usuario autenticado — es la misma.
     */
    public function seleccionarEmpresa(SeleccionarEmpresaRequest $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $empresaId = (int) $request->validated('empresa_id');

        $request->session()->put('empresa_activa_id', $empresaId);

        $this->auditLogger->registrar(AuditAction::CambioEmpresaActiva, $usuario, $empresaId);

        $empresa = $usuario->empresas()->where('companies.id', $empresaId)->first(self::COLUMNAS_EMPRESA);

        return ApiResponse::success($this->empresaArray($empresa));
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->solicitar($request->string('email')->toString());

        // Mensaje genérico siempre, exista o no la cuenta.
        return ApiResponse::success([
            'message' => 'Si el correo está registrado, enviamos un enlace para restablecer la contraseña.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->restablecer(
            $request->string('email')->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        return ApiResponse::success(['message' => 'Contraseña actualizada.']);
    }

    private function respuestaConEmpresas(Request $request, User $usuario): JsonResponse
    {
        // Aunque master no dependa de la membresía para operar dentro de una
        // empresa (ver ResolveEmpresaActiva/EmpresaScope), la lista para
        // elegir empresa activa refleja sus membresías reales, no todo el
        // catálogo de empresas del sistema.
        $empresas = $usuario->empresas()
            ->wherePivot('status', true)
            ->get(self::COLUMNAS_EMPRESA);

        // Con una sola empresa disponible no tiene sentido pedirle al
        // usuario que "seleccione" — se activa sola.
        if ($empresas->count() === 1 && $request->session()->get('empresa_activa_id') === null) {
            $request->session()->put('empresa_activa_id', $empresas->first()->id);
        }

        $empresaActivaId = $request->session()->get('empresa_activa_id');

        return ApiResponse::success([
            'usuario' => $this->usuarioArray($usuario),
            'empresas' => $empresas->map(fn (Company $empresa) => $this->empresaArray($empresa)),
            'empresa_activa_id' => $empresaActivaId,
            'requiere_seleccion_empresa' => $empresas->count() > 1 && $empresaActivaId === null,
        ]);
    }

    /**
     * Mismos nombres que las columnas reales (users.*) — sin traducir a
     * español — salvo `role_name`, que no es una columna sino el
     * roles.name ya resuelto para el role_id de este usuario.
     *
     * @return array<string, mixed>
     */
    private function usuarioArray(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'uuid' => $usuario->uuid,
            'email' => $usuario->email,
            'first_name' => $usuario->first_name,
            'last_name' => $usuario->last_name,
            'status' => $usuario->status,
            'role_id' => $usuario->role_id,
            'role_name' => Role::nombreDe($usuario->role_id),
            'mfa_enabled' => $usuario->mfa_enabled,
        ];
    }

    /**
     * Mismos nombres que companies.* — `membership_status`/`permissions`
     * vienen de company_user (prefijo para no chocar con `status`, que ya
     * es el de companies).
     *
     * @return array<string, mixed>
     */
    private function empresaArray(Company $empresa): array
    {
        $pivot = $empresa->pivot ?? null;

        return [
            'id' => $empresa->id,
            'uuid' => $empresa->uuid,
            'legal_name' => $empresa->legal_name,
            'rut' => $empresa->rut,
            'status' => $empresa->status,
            'membership_status' => $pivot?->status,
            'permissions' => $pivot !== null ? Permisos::clavesDesdeBitmask($pivot->permission) : [],
        ];
    }
}
