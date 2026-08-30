<?php

namespace App\Services\FormBuilder\FieldTypes;

final class FileFieldTypeStrategy extends AbstractFieldTypeStrategy
{
    public function supportedCodes(): array
    {
        return ['file'];
    }

    protected function settingRules(string $code): array
    {
        return [
            'max_files' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'max_size_mb' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'allowed_extensions' => ['sometimes', 'array', 'max:30'],
            'allowed_extensions.*' => ['required', 'string', 'regex:/^[a-z0-9]+$/', 'max:20'],
        ];
    }

    protected function defaultSettings(string $code): array
    {
        return [
            'max_files' => 1,
            'max_size_mb' => 10,
            'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
        ];
    }

    protected function validateConsistency(string $code, array $settings, array $validationRules): void
    {
        if (count($settings['allowed_extensions']) !== count(array_unique($settings['allowed_extensions']))) {
            $this->fail(['settings.allowed_extensions' => 'Las extensiones permitidas no pueden repetirse.']);
        }
    }
}
