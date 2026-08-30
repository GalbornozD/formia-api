<?php

namespace App\Services\FormBuilder\FieldTypes;

final class RichTextFieldTypeStrategy extends AbstractFieldTypeStrategy
{
    private const INLINE_FORMATS = ['bold', 'italic', 'underline', 'strike'];

    public function supportedCodes(): array
    {
        return ['rich_text'];
    }

    protected function settingRules(string $code): array
    {
        return [
            'min_height' => ['sometimes', 'integer', 'min:96', 'max:480'],
            'toolbar' => ['sometimes', 'string', 'in:minimal,standard'],
        ];
    }

    protected function validationRules(string $code): array
    {
        return [
            'min_length' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'max_length' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ];
    }

    protected function defaultSettings(string $code): array
    {
        return ['min_height' => 140, 'toolbar' => 'standard'];
    }

    protected function validateConsistency(string $code, array $settings, array $validationRules): void
    {
        if (isset($validationRules['min_length'], $validationRules['max_length'])
            && $validationRules['min_length'] > $validationRules['max_length']) {
            $this->fail(['validation_rules.min_length' => 'El largo minimo no puede superar el maximo.']);
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

        if (! is_array($defaultValue) || ! isset($defaultValue['ops']) || ! is_array($defaultValue['ops'])) {
            $this->fail(['default_value' => 'El texto enriquecido debe almacenarse como Delta de Quill.']);
        }

        if (count($defaultValue['ops']) > 2000) {
            $this->fail(['default_value' => 'El texto enriquecido contiene demasiadas operaciones.']);
        }

        $ops = [];
        $plainLength = 0;

        foreach ($defaultValue['ops'] as $operation) {
            if (! is_array($operation) || ! isset($operation['insert']) || ! is_string($operation['insert'])) {
                $this->fail(['default_value' => 'El Delta solo admite inserciones de texto.']);
            }

            $plainLength += strlen($operation['insert']);
            if ($plainLength > 100000) {
                $this->fail(['default_value' => 'El texto enriquecido supera el largo permitido.']);
            }

            $normalized = ['insert' => $operation['insert']];
            $attributes = $this->sanitizeAttributes($operation['attributes'] ?? null);
            if ($attributes !== []) {
                $normalized['attributes'] = $attributes;
            }
            $ops[] = $normalized;
        }

        if (isset($validationRules['min_length']) && $plainLength < $validationRules['min_length']) {
            $this->fail(['default_value' => 'El texto enriquecido no alcanza el largo minimo.']);
        }
        if (isset($validationRules['max_length']) && $plainLength > $validationRules['max_length']) {
            $this->fail(['default_value' => 'El texto enriquecido supera el largo maximo.']);
        }

        return ['ops' => $ops];
    }

    /** @return array<string, bool|int|string> */
    private function sanitizeAttributes(mixed $attributes): array
    {
        if (! is_array($attributes)) {
            return [];
        }

        $safe = [];
        foreach (self::INLINE_FORMATS as $format) {
            if (($attributes[$format] ?? null) === true) {
                $safe[$format] = true;
            }
        }

        if (in_array($attributes['header'] ?? null, [1, 2], true)) {
            $safe['header'] = $attributes['header'];
        }
        if (in_array($attributes['list'] ?? null, ['ordered', 'bullet'], true)) {
            $safe['list'] = $attributes['list'];
        }
        if (in_array($attributes['align'] ?? null, ['center', 'right', 'justify'], true)) {
            $safe['align'] = $attributes['align'];
        }

        $link = $attributes['link'] ?? null;
        if (is_string($link) && $this->isSafeLink($link)) {
            $safe['link'] = $link;
        }

        return $safe;
    }

    private function isSafeLink(string $link): bool
    {
        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));

        if (in_array($scheme, ['http', 'https'], true)) {
            return filter_var($link, FILTER_VALIDATE_URL) !== false;
        }

        return $scheme === 'mailto'
            && filter_var(substr($link, strlen('mailto:')), FILTER_VALIDATE_EMAIL) !== false;
    }
}
