<?php

namespace App\Policies;

use App\Models\FormInvitation;
use App\Models\FormPublication;
use App\Models\User;
use App\Support\EmpresaContext;

class FormInvitationPolicy
{
    public function __construct(private readonly EmpresaContext $context) {}

    public function viewAny(User $user, FormPublication $publication): bool
    {
        return $this->canManageCompany($user, $publication->company_id);
    }

    public function view(User $user, FormInvitation $formInvitation): bool
    {
        return $this->canManageCompany($user, $formInvitation->company_id);
    }

    public function update(User $user, FormInvitation $formInvitation): bool
    {
        return $this->canManageCompany($user, $formInvitation->company_id);
    }

    private function canManageCompany(User $user, int $companyId): bool
    {
        return $user->esMaster()
            || ($user->esAdministrador() && $companyId === $this->context->empresaId());
    }
}
