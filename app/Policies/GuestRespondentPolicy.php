<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\GuestRespondent;
use App\Models\User;
use App\Support\EmpresaContext;

class GuestRespondentPolicy
{
    public function __construct(private readonly EmpresaContext $context) {}

    public function viewAny(User $user, Company $empresa): bool
    {
        return $this->canManageCompany($user, $empresa->id);
    }

    public function view(User $user, GuestRespondent $guestRespondent): bool
    {
        return $this->canManageCompany($user, $guestRespondent->company_id);
    }

    public function create(User $user, Company $empresa): bool
    {
        return $this->canManageCompany($user, $empresa->id);
    }

    public function update(User $user, GuestRespondent $guestRespondent): bool
    {
        return $this->canManageCompany($user, $guestRespondent->company_id);
    }

    private function canManageCompany(User $user, int $companyId): bool
    {
        return $user->esMaster()
            || ($user->esAdministrador() && $companyId === $this->context->empresaId());
    }
}
