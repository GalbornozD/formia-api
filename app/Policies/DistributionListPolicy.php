<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\DistributionList;
use App\Models\User;
use App\Support\EmpresaContext;

class DistributionListPolicy
{
    public function __construct(private readonly EmpresaContext $context) {}

    public function viewAny(User $user, Company $empresa): bool
    {
        return $this->canManageCompany($user, $empresa->id);
    }

    public function view(User $user, DistributionList $distributionList): bool
    {
        return $this->canManageCompany($user, $distributionList->company_id);
    }

    public function create(User $user, Company $empresa): bool
    {
        return $this->canManageCompany($user, $empresa->id);
    }

    public function update(User $user, DistributionList $distributionList): bool
    {
        return $this->canManageCompany($user, $distributionList->company_id);
    }

    public function delete(User $user, DistributionList $distributionList): bool
    {
        return $this->update($user, $distributionList);
    }

    private function canManageCompany(User $user, int $companyId): bool
    {
        return $user->esMaster()
            || ($user->esAdministrador() && $companyId === $this->context->empresaId());
    }
}
