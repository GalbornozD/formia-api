<?php

namespace App\Policies;

use App\Models\CompanySettings;
use App\Models\User;
use App\Support\EmpresaContext;

/**
 * Mismo criterio que CompanyBrandingPolicy: cualquier miembro activo puede
 * ver las preferencias regionales de su empresa, solo master/administrador
 * puede modificarlas.
 */
class CompanySettingsPolicy
{
    public function __construct(private readonly EmpresaContext $context) {}

    public function view(User $usuario, CompanySettings $settings): bool
    {
        return $usuario->esMaster() || $settings->company_id === $this->context->empresaId();
    }

    public function update(User $usuario, CompanySettings $settings): bool
    {
        if ($usuario->esMaster()) {
            return true;
        }

        return $usuario->esAdministrador() && $settings->company_id === $this->context->empresaId();
    }
}
