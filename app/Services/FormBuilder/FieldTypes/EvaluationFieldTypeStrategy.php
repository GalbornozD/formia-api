<?php

namespace App\Services\FormBuilder\FieldTypes;

final class EvaluationFieldTypeStrategy extends AbstractFieldTypeStrategy
{
    public function supportedCodes(): array
    {
        return ['slider', 'nps'];
    }

    protected function settingRules(string $code): array
    {
        if ($code === 'nps') {
            return [
                'min_label' => ['sometimes', 'nullable', 'string', 'max:100'],
                'max_label' => ['sometimes', 'nullable', 'string', 'max:100'],
            ];
        }

        return [
            'min' => ['sometimes', 'numeric'],
            'max' => ['sometimes', 'numeric'],
            'step' => ['sometimes', 'numeric', 'min:0.000001'],
            'show_value' => ['sometimes', 'boolean'],
        ];
    }

    protected function defaultSettings(string $code): array
    {
        return $code === 'nps'
            ? ['min_label' => 'Nada probable', 'max_label' => 'Muy probable']
            : ['min' => 0, 'max' => 100, 'step' => 1, 'show_value' => true];
    }

    protected function validateConsistency(string $code, array $settings, array $validationRules): void
    {
        if ($code === 'slider' && $settings['min'] >= $settings['max']) {
            $this->fail(['settings.min' => 'El minimo del slider debe ser menor que el maximo.']);
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

        $minimum = $code === 'nps' ? 0 : $settings['min'];
        $maximum = $code === 'nps' ? 10 : $settings['max'];
        if ($defaultValue < $minimum || $defaultValue > $maximum) {
            $this->fail(['default_value' => 'El valor predeterminado esta fuera del rango permitido.']);
        }
        if ($code === 'nps' && ! is_int($defaultValue)) {
            $this->fail(['default_value' => 'El NPS predeterminado debe ser un entero entre 0 y 10.']);
        }

        return $defaultValue;
    }
}
