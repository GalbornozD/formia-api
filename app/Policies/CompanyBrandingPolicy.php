<?php

namespace App\Policies;

use App\Models\CompanyBranding;
use App\Models\User;
use App\Support\EmpresaContext;

/**
 * Cualquier miembro activo de la empresa puede ver su branding (lo necesita
 * para renderizar la aplicación). Solo master/administrador puede editarlo
 * -- mismo criterio de rol global que FormTypePolicy/CompanyUserPolicy.
 */
class CompanyBrandingPolicy
{
    public function __construct(private readonly EmpresaContext $context) {}

    public function view(User $usuario, CompanyBranding $branding): bool
    {
        return $usuario->esMaster() || $branding->company_id === $this->context->empresaId();
    }

    public function update(User $usuario, CompanyBranding $branding): bool
    {
        if ($usuario->esMaster()) {
            return true;
        }

        return $usuario->esAdministrador() && $branding->company_id === $this->context->empresaId();
    }
}
