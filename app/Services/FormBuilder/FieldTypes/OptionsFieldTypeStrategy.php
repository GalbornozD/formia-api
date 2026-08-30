<?php

namespace App\Services\FormBuilder\FieldTypes;

final class OptionsFieldTypeStrategy extends AbstractFieldTypeStrategy
{
    public function supportedCodes(): array
    {
        return ['select', 'autocomplete', 'multiselect', 'radio', 'checkbox', 'likert'];
    }

    protected function settingRules(string $code): array
    {
        if (! in_array($code, ['select', 'autocomplete', 'multiselect'], true)) {
            return [];
        }

        return [
            'searchable' => ['sometimes', 'boolean'],
            'allow_clear' => ['sometimes', 'boolean'],
        ];
    }

    protected function defaultSettings(string $code): array
    {
        return in_array($code, ['select', 'autocomplete', 'multiselect'], true)
            ? ['searchable' => false, 'allow_clear' => true]
            : [];
    }
}
