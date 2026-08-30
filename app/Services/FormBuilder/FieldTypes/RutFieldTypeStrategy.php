<?php

namespace App\Services\FormBuilder\FieldTypes;

use App\Services\FormBuilder\Validation\ChileanRut;

final class RutFieldTypeStrategy extends AbstractFieldTypeStrategy
{
    public function supportedCodes(): array
    {
        return ['rut'];
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

        if (! is_string($defaultValue) || ! ChileanRut::isValid($defaultValue)) {
            $this->fail(['default_value' => 'El RUT predeterminado no es valido.']);
        }

        return ChileanRut::normalize($defaultValue);
    }
}
