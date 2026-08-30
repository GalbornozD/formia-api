<?php

namespace App\Services\FormBuilder\FieldTypes;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

abstract class AbstractFieldTypeStrategy implements FieldTypeStrategy
{
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
    ): array {
        $this->rejectUnknownKeys('settings', $settings, $this->allowedSettingKeys($code));
        $this->rejectUnknownKeys('validation_rules', $validationRules, array_keys($this->validationRules($code)));

        Validator::make(
            ['settings' => $settings, 'validation_rules' => $validationRules],
            $this->qualifiedRules($code),
        )->validate();

        $settings = [...$this->defaultSettings($code), ...$settings];
        $validationRules = [...$this->defaultValidationRules($code), ...$validationRules];

        $this->validateConsistency($code, $settings, $validationRules);

        return [
            'settings' => $settings,
            'validation_rules' => $validationRules,
            'default_value' => $this->normalizeDefaultValue(
                $code,
                $defaultValue,
                $settings,
                $validationRules,
            ),
        ];
    }

    /** @return array<string, list<string>|string> */
    protected function settingRules(string $code): array
    {
        return [];
    }

    /** @return array<string, list<string>|string> */
    protected function validationRules(string $code): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function defaultSettings(string $code): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function defaultValidationRules(string $code): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $validationRules
     */
    protected function validateConsistency(string $code, array $settings, array $validationRules): void {}

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $validationRules
     */
    protected function normalizeDefaultValue(
        string $code,
        mixed $defaultValue,
        array $settings,
        array $validationRules,
    ): mixed {
        return $defaultValue;
    }

    /** @param array<string, string> $messages */
    protected function fail(array $messages): never
    {
        throw ValidationException::withMessages($messages);
    }

    /** @return list<string> */
    private function allowedSettingKeys(string $code): array
    {
        return array_values(array_unique(array_map(
            static fn (string $key): string => str_ends_with($key, '.*') ? substr($key, 0, -2) : $key,
            array_keys($this->settingRules($code)),
        )));
    }

    /** @return array<string, list<string>|string> */
    private function qualifiedRules(string $code): array
    {
        $rules = ['settings' => ['array'], 'validation_rules' => ['array']];

        foreach ($this->settingRules($code) as $key => $keyRules) {
            $rules["settings.{$key}"] = $keyRules;
        }

        foreach ($this->validationRules($code) as $key => $keyRules) {
            $rules["validation_rules.{$key}"] = $keyRules;
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $allowedKeys
     */
    private function rejectUnknownKeys(string $attribute, array $values, array $allowedKeys): void
    {
        $unknownKeys = array_values(array_diff(array_keys($values), $allowedKeys));

        if ($unknownKeys !== []) {
            throw ValidationException::withMessages([
                $attribute => 'La configuración contiene atributos no permitidos: '.implode(', ', $unknownKeys).'.',
            ]);
        }
    }
}
