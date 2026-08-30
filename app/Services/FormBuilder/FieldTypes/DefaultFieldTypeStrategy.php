<?php

namespace App\Services\FormBuilder\FieldTypes;

final class DefaultFieldTypeStrategy extends AbstractFieldTypeStrategy
{
    public function supportedCodes(): array
    {
        return ['date', 'time', 'datetime', 'signature', 'paragraph', 'yes_no', 'divider'];
    }

    protected function normalizeDefaultValue(
        string $code,
        mixed $defaultValue,
        array $settings,
        array $validationRules,
    ): mixed {
        if ($code === 'yes_no' && $defaultValue !== null && ! is_bool($defaultValue)) {
            $this->fail(['default_value' => 'El valor predeterminado de Si / No debe ser booleano.']);
        }

        if (in_array($code, ['paragraph', 'divider'], true) && $defaultValue !== null) {
            $this->fail(['default_value' => 'Este tipo estructural no admite un valor predeterminado.']);
        }

        return $defaultValue;
    }
}
