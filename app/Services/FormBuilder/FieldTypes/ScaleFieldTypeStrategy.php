<?php

namespace App\Services\FormBuilder\FieldTypes;

final class ScaleFieldTypeStrategy extends AbstractFieldTypeStrategy
{
    public function supportedCodes(): array
    {
        return ['scale'];
    }

    protected function settingRules(string $code): array
    {
        return [
            'min' => ['sometimes', 'integer', 'min:-1000', 'max:1000'],
            'max' => ['sometimes', 'integer', 'min:-1000', 'max:1000'],
            'step' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'min_label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'max_label' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    protected function defaultSettings(string $code): array
    {
        return ['min' => 1, 'max' => 10, 'step' => 1];
    }

    protected function validateConsistency(string $code, array $settings, array $validationRules): void
    {
        if ($settings['min'] >= $settings['max']) {
            $this->fail(['settings.min' => 'El mínimo de la escala debe ser menor que el máximo.']);
        }

        if (($settings['max'] - $settings['min']) % $settings['step'] !== 0) {
            $this->fail(['settings.step' => 'El paso debe dividir exactamente el rango de la escala.']);
        }
    }
}
