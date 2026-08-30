<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\FormType;
use App\Models\User;
use App\Support\EmpresaContext;

/**
 * Autoriza CRUD de tipos de formulario por rol global -- mismo criterio que
 * CompanyUserPolicy: master gestiona cualquier empresa, administrador solo
 * la suya. No hay lectura para otros roles: esta es una pantalla de
 * configuracion, no un catalogo de consulta general para todo miembro.
 */
class FormTypePolicy
{
    public function __construct(private readonly EmpresaContext $context) {}

    public function viewAny(User $usuario, Company $empresa): bool
    {
        return $usuario->esMaster() || ($usuario->esAdministrador() && $this->esEmpresaActiva($empresa));
    }

    public function create(User $usuario, Company $empresa): bool
    {
        return $this->viewAny($usuario, $empresa);
    }

    public function update(User $usuario, FormType $tipoFormulario): bool
    {
        if ($usuario->esMaster()) {
            return true;
        }

        return $usuario->esAdministrador() && $tipoFormulario->company_id === $this->context->empresaId();
    }

    public function delete(User $usuario, FormType $tipoFormulario): bool
    {
        return $this->update($usuario, $tipoFormulario);
    }

    private function esEmpresaActiva(Company $empresa): bool
    {
        return $empresa->id === $this->context->empresaId();
    }
}
