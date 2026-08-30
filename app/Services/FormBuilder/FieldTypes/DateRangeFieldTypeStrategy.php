<?php

namespace App\Services\FormBuilder\FieldTypes;

use DateTimeImmutable;

final class DateRangeFieldTypeStrategy extends AbstractFieldTypeStrategy
{
    public function supportedCodes(): array
    {
        return ['date_range'];
    }

    protected function settingRules(string $code): array
    {
        return [
            'allow_same_day' => ['sometimes', 'boolean'],
            'min_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'max_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    protected function defaultSettings(string $code): array
    {
        return ['allow_same_day' => true];
    }

    protected function validateConsistency(string $code, array $settings, array $validationRules): void
    {
        if (isset($settings['min_date'], $settings['max_date'])
            && $settings['min_date'] > $settings['max_date']) {
            $this->fail(['settings.min_date' => 'La fecha minima no puede superar la maxima.']);
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

        if (! is_array($defaultValue)
            || ! $this->isDate($defaultValue['start'] ?? null)
            || ! $this->isDate($defaultValue['end'] ?? null)) {
            $this->fail(['default_value' => 'El rango predeterminado debe incluir start y end en formato Y-m-d.']);
        }

        $start = $defaultValue['start'];
        $end = $defaultValue['end'];
        if ($start > $end || (! $settings['allow_same_day'] && $start === $end)) {
            $this->fail(['default_value' => 'La fecha de inicio debe ser anterior a la fecha de termino.']);
        }
        if (isset($settings['min_date']) && $start < $settings['min_date']) {
            $this->fail(['default_value.start' => 'La fecha de inicio es menor que el minimo permitido.']);
        }
        if (isset($settings['max_date']) && $end > $settings['max_date']) {
            $this->fail(['default_value.end' => 'La fecha de termino supera el maximo permitido.']);
        }

        return ['start' => $start, 'end' => $end];
    }

    private function isDate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
