<?php

namespace App\Services\FormResponses;

use App\Models\FormField;

class FieldVisibilityResolver
{
    public function isVisible(FormField $field): bool
    {
        return $field->is_active && ! $field->is_hidden;
    }

    public function isStructural(FormField $field): bool
    {
        $code = (string) $field->fieldType->code;

        return in_array($code, ['section', 'paragraph', 'divider'], true);
    }

    public function isContainerAnswer(FormField $field): bool
    {
        return in_array((string) $field->fieldType->code, ['table', 'repeatable_group'], true);
    }

    public function isResponseable(FormField $field): bool
    {
        return $this->isVisible($field) && (! $this->isStructural($field) || $this->isContainerAnswer($field));
    }
}
