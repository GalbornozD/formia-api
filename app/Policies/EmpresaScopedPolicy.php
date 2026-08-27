<?php

namespace App\Policies;

use App\Support\EmpresaContext;

/**
 * Base para policies de modelos de negocio (inspecciones, reportes, etc.)
 * que necesitan el bitmask `permission` de la empresa ACTIVA (EmpresaContext).
 * El rol global (master/administrador) NO vive aquí — se chequea
 * directamente sobre el User inyectado en cada método de la policy
 * concreta (User::esMaster()/esAdministrador()).
 */
abstract class EmpresaScopedPolicy
{
    public function __construct(protected readonly EmpresaContext $context) {}

    protected function tienePermiso(int $bit): bool
    {
        return $this->context->tienePermiso($bit);
    }
}
