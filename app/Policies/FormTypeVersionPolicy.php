<?php

namespace App\Policies;

use App\Models\FormType;
use App\Models\FormTypeVersion;
use App\Models\User;

class FormTypeVersionPolicy
{
    public function __construct(private readonly FormTypePolicy $formTypePolicy) {}

    public function viewAny(User $user, FormType $formType): bool
    {
        return $this->formTypePolicy->update($user, $formType);
    }

    public function view(User $user, FormTypeVersion $version): bool
    {
        return $this->formTypePolicy->update($user, $version->formType);
    }

    public function create(User $user, FormType $formType): bool
    {
        return $this->formTypePolicy->update($user, $formType);
    }

    public function update(User $user, FormTypeVersion $version): bool
    {
        return $this->view($user, $version);
    }

    public function publish(User $user, FormTypeVersion $version): bool
    {
        return $this->update($user, $version);
    }
}
