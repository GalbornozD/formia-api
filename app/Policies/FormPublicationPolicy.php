<?php

namespace App\Policies;

use App\Models\FormPublication;
use App\Models\FormType;
use App\Models\User;
use App\Support\EmpresaContext;

class FormPublicationPolicy
{
    public function __construct(
        private readonly EmpresaContext $context,
        private readonly FormTypePolicy $formTypePolicy,
    ) {}

    public function viewAny(User $user, FormType $formType): bool
    {
        return $this->formTypePolicy->update($user, $formType);
    }

    public function view(User $user, FormPublication $formPublication): bool
    {
        return $this->canManageCompany($user, $formPublication->company_id);
    }

    public function create(User $user, FormType $formType): bool
    {
        return $this->formTypePolicy->update($user, $formType);
    }

    public function update(User $user, FormPublication $formPublication): bool
    {
        return $this->canManageCompany($user, $formPublication->company_id);
    }

    public function delete(User $user, FormPublication $formPublication): bool
    {
        return $this->update($user, $formPublication);
    }

    private function canManageCompany(User $user, int $companyId): bool
    {
        return $user->esMaster()
            || ($user->esAdministrador() && $companyId === $this->context->empresaId());
    }
}
