<?php

namespace App\Policies;

use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\User;

class FormFieldOptionPolicy
{
    public function __construct(private readonly FormFieldPolicy $fieldPolicy) {}

    public function view(User $user, FormFieldOption $option): bool
    {
        return $this->fieldPolicy->view($user, $option->formField);
    }

    public function create(User $user, FormField $field): bool
    {
        return $this->fieldPolicy->update($user, $field);
    }

    public function update(User $user, FormFieldOption $option): bool
    {
        return $this->view($user, $option);
    }

    public function delete(User $user, FormFieldOption $option): bool
    {
        return $this->update($user, $option);
    }
}
