<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alta/edición/baja de usuarios de una empresa. La comparten el comando
 * `usuario:crear` (CLI) y EmpresaUsuarioController (API), para no repetir
 * la transacción de "crear User + membresía company_user".
 */
class UsuarioService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{email: string, first_name: string, last_name: string, password: string, role_id: int, permission?: int}  $datos
     */
    public function crear(Company $empresa, array $datos, ?User $actor = null): CompanyUser
    {
        $companyUser = DB::transaction(function () use ($empresa, $datos) {
            // forceFill(): password_hash/status/role_id quedan fuera de
            // $fillable a propósito (para que ningún formulario público
            // pueda mass-asignarlos); la validación real ya ocurrió en el
            // FormRequest o en el comando, antes de llegar acá.
            $usuario = new User;
            $usuario->forceFill([
                'uuid' => (string) Str::uuid(),
                'role_id' => $datos['role_id'],
                'email' => $datos['email'],
                'email_verified_at' => now(),
                'password_hash' => $datos['password'],
                'first_name' => $datos['first_name'],
                'last_name' => $datos['last_name'],
                'status' => true,
            ])->save();

            // attach() hace un insert directo (no dispara CompanyUserObserver),
            // por eso se audita explícitamente abajo.
            $usuario->empresas()->attach($empresa->id, [
                'permission' => $datos['permission'] ?? 15,
                'status' => true,
            ]);

            // setPivotKeys(): sin esto, un save()/delete() posterior sobre
            // esta instancia no sabría scopear su query (el pivot no tiene
            // columna `id`) y terminaría operando sin WHERE.
            return CompanyUser::where('user_id', $usuario->id)
                ->where('company_id', $empresa->id)
                ->firstOrFail(['user_id', 'company_id', 'permission', 'status'])
                ->setPivotKeys('user_id', 'company_id');
        });

        $this->auditLogger->registrar(
            accion: AuditAction::MembresiaCreada,
            usuario: $actor,
            empresaId: $empresa->id,
            entidad: 'company_user',
            entidadId: "{$companyUser->user_id}:{$companyUser->company_id}",
            detalle: ['role_id' => $datos['role_id']],
        );

        return $companyUser->load('usuario:id,uuid,email,first_name,last_name,status,role_id');
    }

    /**
     * updated()/deleted() en CompanyUserObserver ya auditan estos cambios
     * (a diferencia de crear(), acá no se usa attach()), así que no hace
     * falta loguear de nuevo.
     *
     * @param  array{first_name?: string, last_name?: string, role_id?: int, permission?: int, status?: bool}  $datos
     */
    public function actualizar(CompanyUser $companyUser, array $datos): CompanyUser
    {
        DB::transaction(function () use ($companyUser, $datos) {
            $companyUser->usuario->forceFill(array_filter([
                'first_name' => $datos['first_name'] ?? null,
                'last_name' => $datos['last_name'] ?? null,
                'role_id' => $datos['role_id'] ?? null,
            ], fn ($valor) => $valor !== null))->save();

            $companyUser->forceFill(array_filter([
                'permission' => $datos['permission'] ?? null,
                'status' => $datos['status'] ?? null,
            ], fn ($valor) => $valor !== null))->save();
        });

        // No usar refresh() ni volver a hacer load('usuario'): Pivot no tiene
        // columna `id` (clave compuesta user_id+company_id), así que
        // Model::refresh() -> findOrFail(getKey()) buscaría `id = null` y
        // fallaría; y la relación `usuario` ya viene cargada (con columnas
        // acotadas) desde companyUserOrFail(), con los atributos al día tras
        // los save() de arriba — recargarla solo pisaría ese select acotado
        // por uno sin restricción.
        return $companyUser;
    }

    public function eliminar(CompanyUser $companyUser): void
    {
        $companyUser->delete();
    }
}
