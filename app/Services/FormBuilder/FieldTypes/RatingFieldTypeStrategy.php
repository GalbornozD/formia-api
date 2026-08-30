<?php

namespace App\Services\FormBuilder\FieldTypes;

final class RatingFieldTypeStrategy extends AbstractFieldTypeStrategy
{
    public function supportedCodes(): array
    {
        return ['rating'];
    }

    protected function settingRules(string $code): array
    {
        return [
            'max' => ['sometimes', 'integer', 'min:2', 'max:10'],
            'icon' => ['sometimes', 'string', 'in:star,heart,thumb'],
        ];
    }

    protected function defaultSettings(string $code): array
    {
        return ['max' => 5, 'icon' => 'star'];
    }
}
