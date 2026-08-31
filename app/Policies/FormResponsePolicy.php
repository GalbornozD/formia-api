<?php

namespace App\Policies;

use App\Enums\RespondentType;
use App\Models\FormResponse;
use App\Models\User;
use App\Support\EmpresaContext;

class FormResponsePolicy
{
    public function __construct(private readonly EmpresaContext $context) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FormResponse $formResponse): bool
    {
        return $this->ownsResponse($user, $formResponse) || $this->canManageCompany($user, $formResponse->company_id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, FormResponse $formResponse): bool
    {
        return $this->ownsResponse($user, $formResponse);
    }

    public function delete(User $user, FormResponse $formResponse): bool
    {
        return false;
    }

    private function ownsResponse(User $user, FormResponse $formResponse): bool
    {
        return $formResponse->respondent_type === RespondentType::User
            && $formResponse->user_id === $user->id;
    }

    private function canManageCompany(User $user, int $companyId): bool
    {
        return $user->esMaster()
            || ($user->esAdministrador() && $companyId === $this->context->empresaId());
    }
}
