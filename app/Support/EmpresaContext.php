<?php

namespace App\Support;

use App\Models\Company;
use App\Models\CompanyUser;

/**
 * Empresa activa resuelta para el request actual por ResolveEmpresaActiva.
 * Vive en el container como singleton (scope = 1 request), nunca en sesión
 * ni en el token: así el backend es la única fuente de verdad y el cliente
 * no puede forzar un empresa_id que no le pertenece.
 *
 * `master` es la única excepción al filtro por empresa: un usuario con ese
 * rol global (users.role_id) opera sin restricción de empresa activa.
 */
final class EmpresaContext
{
    private ?Company $empresa = null;

    private ?CompanyUser $membresia = null;

    private bool $master = false;

    public function set(Company $empresa, ?CompanyUser $membresia = null): void
    {
        $this->empresa = $empresa;
        $this->membresia = $membresia;
    }

    public function setMaster(): void
    {
        $this->master = true;
    }

    public function isMaster(): bool
    {
        return $this->master;
    }

    public function resolved(): bool
    {
        return $this->master || $this->empresa !== null;
    }

    public function empresaId(): ?int
    {
        return $this->empresa?->id;
    }

    public function empresa(): ?Company
    {
        return $this->empresa;
    }

    public function membresia(): ?CompanyUser
    {
        return $this->membresia;
    }

    public function tienePermiso(int $bit): bool
    {
        return $this->membresia !== null && ($this->membresia->permission & $bit) === $bit;
    }
}
