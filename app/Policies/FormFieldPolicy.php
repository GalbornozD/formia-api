<?php

namespace App\Policies;

use App\Models\FormField;
use App\Models\FormTypeVersion;
use App\Models\User;

class FormFieldPolicy
{
    public function __construct(private readonly FormTypeVersionPolicy $versionPolicy) {}

    public function viewAny(User $user, FormTypeVersion $version): bool
    {
        return $this->versionPolicy->view($user, $version);
    }

    public function view(User $user, FormField $field): bool
    {
        return $this->versionPolicy->view($user, $field->formTypeVersion);
    }

    public function create(User $user, FormTypeVersion $version): bool
    {
        return $this->versionPolicy->update($user, $version);
    }

    public function update(User $user, FormField $field): bool
    {
        return $this->view($user, $field);
    }

    public function delete(User $user, FormField $field): bool
    {
        return $this->update($user, $field);
    }
}
