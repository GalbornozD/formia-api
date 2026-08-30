<?php

namespace App\Services\FormBuilder\FieldTypes;

interface FieldTypeStrategy
{
    /**
     * @return list<string>
     */
    public function supportedCodes(): array;

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $validationRules
     * @return array{settings: array<string, mixed>, validation_rules: array<string, mixed>, default_value: mixed}
     */
    public function validateAndNormalize(
        string $code,
        array $settings,
        array $validationRules,
        mixed $defaultValue = null,
    ): array;
}
