<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Support\EmpresaContext;

/**
 * Autoriza CRUD de company_user (usuarios de la empresa) por rol global:
 * - master: acceso total, cualquier empresa.
 * - administrador: puede gestionar usuarios, pero solo de su empresa activa,
 *   y nunca a un usuario con más poder que el propio — role_id menor implica
 *   más poder (master=1 es el techo), así que esto generaliza "un
 *   administrador no puede tocar a un master" a cualquier par de roles.
 * El bitmask `permission` no participa acá — es para autorización fina de
 * otros módulos (ej. solicitudes), a definir más adelante.
 */
class CompanyUserPolicy
{
    public function __construct(private readonly EmpresaContext $context) {}

    public function viewAny(User $usuario, Company $empresa): bool
    {
        return $usuario->esMaster() || $this->esEmpresaActiva($empresa);
    }

    public function view(User $usuario, CompanyUser $companyUser): bool
    {
        return $usuario->esMaster() || $companyUser->company_id === $this->context->empresaId();
    }

    public function create(User $usuario, Company $empresa): bool
    {
        return $usuario->esMaster() || ($usuario->esAdministrador() && $this->esEmpresaActiva($empresa));
    }

    public function update(User $usuario, CompanyUser $companyUser): bool
    {
        if ($usuario->esMaster()) {
            return true;
        }

        return $usuario->esAdministrador()
            && $this->noSuperaEnPoderAlActor($usuario, $companyUser)
            && $companyUser->company_id === $this->context->empresaId();
    }

    public function delete(User $usuario, CompanyUser $companyUser): bool
    {
        return $this->update($usuario, $companyUser);
    }

    private function esEmpresaActiva(Company $empresa): bool
    {
        return $empresa->id === $this->context->empresaId();
    }

    /**
     * true si el rol actual del usuario objetivo NO tiene más poder que el
     * del actor (role_id mayor o igual — recordar que role_id menor = más
     * poder). El nuevo rol que se le quiera asignar ya queda acotado aparte
     * por Role::asignablesPor() en la validación del request.
     */
    private function noSuperaEnPoderAlActor(User $actor, CompanyUser $companyUser): bool
    {
        return $companyUser->usuario->role_id >= $actor->role_id;
    }
}
