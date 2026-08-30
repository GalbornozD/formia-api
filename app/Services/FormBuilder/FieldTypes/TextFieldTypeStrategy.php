<?php

namespace App\Services\FormBuilder\FieldTypes;

final class TextFieldTypeStrategy extends AbstractFieldTypeStrategy
{
    public function supportedCodes(): array
    {
        return ['text', 'textarea', 'email', 'phone', 'url'];
    }

    protected function settingRules(string $code): array
    {
        return $code === 'text' ? ['autocomplete' => ['sometimes', 'boolean']] : [];
    }

    protected function validationRules(string $code): array
    {
        return [
            'min_length' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'max_length' => ['sometimes', 'integer', 'min:1', 'max:65535'],
        ];
    }

    protected function defaultSettings(string $code): array
    {
        return $code === 'text' ? ['autocomplete' => false] : [];
    }

    protected function validateConsistency(string $code, array $settings, array $validationRules): void
    {
        if (isset($validationRules['min_length'], $validationRules['max_length'])
            && $validationRules['min_length'] > $validationRules['max_length']) {
            $this->fail(['validation_rules.min_length' => 'El largo mínimo no puede superar el largo máximo.']);
        }
    }

    protected function normalizeDefaultValue(
        string $code,
        mixed $defaultValue,
        array $settings,
        array $validationRules,
    ): mixed {
        if ($defaultValue === null) {
            return null;
        }

        if (! is_string($defaultValue)) {
            $this->fail(['default_value' => 'El valor predeterminado debe ser texto.']);
        }

        if ($code === 'url') {
            $scheme = parse_url($defaultValue, PHP_URL_SCHEME);
            if (! filter_var($defaultValue, FILTER_VALIDATE_URL)
                || ! in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
                $this->fail(['default_value' => 'La URL predeterminada debe usar http o https.']);
            }
        }

        return $defaultValue;
    }
}
