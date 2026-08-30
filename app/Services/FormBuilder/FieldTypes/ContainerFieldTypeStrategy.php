<?php

namespace App\Services\FormBuilder\FieldTypes;

final class ContainerFieldTypeStrategy extends AbstractFieldTypeStrategy
{
    public function supportedCodes(): array
    {
        return ['table', 'section', 'repeatable_group'];
    }

    protected function settingRules(string $code): array
    {
        if ($code === 'repeatable_group') {
            return [
                'min_items' => ['sometimes', 'integer', 'min:0', 'max:100'],
                'max_items' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'allow_add' => ['sometimes', 'boolean'],
                'allow_remove' => ['sometimes', 'boolean'],
            ];
        }

        return $code === 'table'
            ? [
                'allow_add_rows' => ['sometimes', 'boolean'],
                'allow_delete_rows' => ['sometimes', 'boolean'],
                'min_rows' => ['sometimes', 'integer', 'min:0', 'max:1000'],
                'max_rows' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            ]
            : [];
    }

    protected function defaultSettings(string $code): array
    {
        return match ($code) {
            'table' => ['allow_add_rows' => true, 'allow_delete_rows' => true, 'min_rows' => 1, 'max_rows' => 20],
            'repeatable_group' => ['min_items' => 1, 'max_items' => 10, 'allow_add' => true, 'allow_remove' => true],
            default => [],
        };
    }

    protected function validateConsistency(string $code, array $settings, array $validationRules): void
    {
        if ($code === 'repeatable_group' && $settings['min_items'] > $settings['max_items']) {
            $this->fail(['settings.min_items' => 'La cantidad minima de elementos no puede superar el maximo.']);
        }

        if ($code === 'table' && $settings['min_rows'] > $settings['max_rows']) {
            $this->fail(['settings.min_rows' => 'La cantidad mínima de filas no puede superar el máximo.']);
        }
    }
}
