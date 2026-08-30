<?php

namespace App\Services\FormBuilder\FieldTypes;

final class NumberFieldTypeStrategy extends AbstractFieldTypeStrategy
{
    public function supportedCodes(): array
    {
        return ['number', 'currency', 'percentage'];
    }

    protected function settingRules(string $code): array
    {
        return $code === 'currency'
            ? ['currency' => ['sometimes', 'string', 'in:CLP,USD,EUR']]
            : [];
    }

    protected function validationRules(string $code): array
    {
        return [
            'min' => ['sometimes', 'numeric'],
            'max' => ['sometimes', 'numeric'],
            'decimals' => ['sometimes', 'integer', 'min:0', 'max:8'],
        ];
    }

    protected function defaultValidationRules(string $code): array
    {
        return $code === 'percentage'
            ? ['min' => 0, 'max' => 100, 'decimals' => 0]
            : ['decimals' => 0];
    }

    protected function defaultSettings(string $code): array
    {
        return $code === 'currency' ? ['currency' => 'CLP'] : [];
    }

    protected function validateConsistency(string $code, array $settings, array $validationRules): void
    {
        if (isset($validationRules['min'], $validationRules['max'])
            && $validationRules['min'] > $validationRules['max']) {
            $this->fail(['validation_rules.min' => 'El valor mínimo no puede superar el máximo.']);
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

        if (! is_int($defaultValue) && ! is_float($defaultValue)) {
            $this->fail(['default_value' => 'El valor predeterminado debe ser numerico.']);
        }

        if (isset($validationRules['min']) && $defaultValue < $validationRules['min']) {
            $this->fail(['default_value' => 'El valor predeterminado no puede ser menor que el minimo.']);
        }

        if (isset($validationRules['max']) && $defaultValue > $validationRules['max']) {
            $this->fail(['default_value' => 'El valor predeterminado no puede superar el maximo.']);
        }

        return $defaultValue;
    }
}
