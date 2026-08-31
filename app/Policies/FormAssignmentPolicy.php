<?php

namespace App\Policies;

use App\Models\FormAssignment;
use App\Models\FormPublication;
use App\Models\User;
use App\Support\EmpresaContext;

class FormAssignmentPolicy
{
    public function __construct(private readonly EmpresaContext $context) {}

    public function viewAny(User $user, FormPublication $publication): bool
    {
        return $this->canManageCompany($user, $publication->company_id);
    }

    public function view(User $user, FormAssignment $formAssignment): bool
    {
        return $this->canManageCompany($user, $formAssignment->company_id)
            || $formAssignment->user_id === $user->id;
    }

    public function create(User $user, FormPublication $publication): bool
    {
        return $this->canManageCompany($user, $publication->company_id);
    }

    public function update(User $user, FormAssignment $formAssignment): bool
    {
        return $this->canManageCompany($user, $formAssignment->company_id);
    }

    public function delete(User $user, FormAssignment $formAssignment): bool
    {
        return $this->update($user, $formAssignment);
    }

    private function canManageCompany(User $user, int $companyId): bool
    {
        return $user->esMaster()
            || ($user->esAdministrador() && $companyId === $this->context->empresaId());
    }
}
