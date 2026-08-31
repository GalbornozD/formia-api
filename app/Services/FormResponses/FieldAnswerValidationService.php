<?php

namespace App\Services\FormResponses;

use App\Casts\SanitizedRichText;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormTypeVersion;
use App\Services\FormBuilder\Validation\ChileanRut;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FieldAnswerValidationService
{
    public function __construct(private readonly FieldVisibilityResolver $visibilityResolver) {}

    /**
     * @param  list<array{field_key: string, value: mixed}>  $answers
     * @return array<int, mixed>
     */
    public function normalize(FormTypeVersion $version, array $answers, bool $submit): array
    {
        $fields = $this->fieldsForVersion($version);
        $fieldsByKey = $fields->keyBy('field_key');
        $fieldsByParent = $fields->groupBy(fn (FormField $field): int => $field->parent_field_id ?? 0);
        $fieldsById = $fields->keyBy('id');
        $errors = [];
        $normalized = [];
        $providedKeys = [];

        foreach ($answers as $answer) {
            $fieldKey = (string) ($answer['field_key'] ?? '');
            $path = "fields.{$fieldKey}";
            $field = $fieldsByKey->get($fieldKey);

            if ($field === null || ! $this->visibilityResolver->isVisible($field)) {
                $errors[$path] = 'El campo no existe o no esta disponible en esta version.';

                continue;
            }

            if ($this->isInsideContainerAnswer($field, $fieldsById)) {
                $errors[$path] = 'El campo debe enviarse dentro de su tabla o grupo repetible.';

                continue;
            }

            $providedKeys[$fieldKey] = true;
            $errorCount = count($errors);
            $value = $this->normalizeField($field, $answer['value'] ?? null, $fieldsByParent, $fieldsById, $submit, $path, $errors);

            if (count($errors) === $errorCount) {
                $normalized[$field->id] = $value;
            }
        }

        if ($submit) {
            foreach ($this->responseableTopLevelFields($fields, $fieldsById) as $field) {
                if (! $field->is_required || isset($providedKeys[$field->field_key])) {
                    continue;
                }

                $errors['fields.'.$field->field_key] = 'Este campo es obligatorio.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    /**
     * @return EloquentCollection<int, FormField>
     */
    private function fieldsForVersion(FormTypeVersion $version): EloquentCollection
    {
        return FormField::query()
            ->where('form_type_version_id', $version->id)
            ->with([
                'fieldType',
                'options' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, EloquentCollection<int, FormField>>  $fieldsByParent
     * @param  Collection<int, FormField>  $fieldsById
     * @param  array<string, string>  $errors
     */
    private function normalizeField(
        FormField $field,
        mixed $value,
        Collection $fieldsByParent,
        Collection $fieldsById,
        bool $submit,
        string $path,
        array &$errors,
    ): mixed {
        $code = (string) $field->fieldType->code;

        if ($this->isEmpty($value, $code)) {
            if ($submit && $field->is_required) {
                $errors[$path] = 'Este campo es obligatorio.';
            }

            return null;
        }

        return match ($code) {
            'text', 'textarea', 'phone' => $this->normalizeText($field, $value, $path, $errors),
            'email' => $this->normalizeEmail($field, $value, $path, $errors),
            'url' => $this->normalizeUrl($field, $value, $path, $errors),
            'rut' => $this->normalizeRut($value, $path, $errors),
            'number', 'currency', 'percentage' => $this->normalizeNumber($field, $value, $path, $errors),
            'date' => $this->normalizeDate($value, $path, $errors),
            'time' => $this->normalizeTime($value, $path, $errors),
            'datetime' => $this->normalizeDatetime($value, $path, $errors),
            'select', 'radio', 'autocomplete', 'likert' => $this->normalizeSingleOption($field, $value, $path, $errors),
            'multiselect', 'checkbox' => $this->normalizeMultipleOptions($field, $value, $path, $errors),
            'yes_no' => $this->normalizeBoolean($value, $path, $errors),
            'rating' => $this->normalizeIntegerRange($value, 1, (int) (($field->settings ?? [])['max'] ?? 5), $path, $errors),
            'scale' => $this->normalizeScale($field, $value, $path, $errors),
            'slider' => $this->normalizeSlider($field, $value, $path, $errors),
            'nps' => $this->normalizeIntegerRange($value, 0, 10, $path, $errors),
            'date_range' => $this->normalizeDateRange($field, $value, $path, $errors),
            'file' => $this->normalizeFileMetadata($field, $value, $path, $errors),
            'signature' => $this->normalizeSignature($value, $path, $errors),
            'rich_text' => $this->normalizeRichText($field, $value, $path, $errors),
            'table', 'repeatable_group' => $this->normalizeRows($field, $value, $fieldsByParent, $fieldsById, $submit, $path, $errors),
            'section', 'paragraph', 'divider' => $this->rejectStructural($path, $errors),
            default => $this->rejectUnsupported($path, $errors),
        };
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normalizeText(FormField $field, mixed $value, string $path, array &$errors): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            $errors[$path] = 'El valor debe ser texto.';

            return null;
        }

        $text = trim((string) $value);
        $length = mb_strlen($text);
        $rules = $field->validation_rules ?? [];

        if (isset($rules['min_length']) && $length < (int) $rules['min_length']) {
            $errors[$path] = 'El texto no alcanza el largo minimo.';
        }

        if (isset($rules['max_length']) && $length > (int) $rules['max_length']) {
            $errors[$path] = 'El texto supera el largo maximo.';
        }

        return $text;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normalizeEmail(FormField $field, mixed $value, string $path, array &$errors): ?string
    {
        $email = $this->normalizeText($field, $value, $path, $errors);

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[$path] = 'El correo electronico no es valido.';
        }

        return $email;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normalizeUrl(FormField $field, mixed $value, string $path, array &$errors): ?string
    {
        $url = $this->normalizeText($field, $value, $path, $errors);
        $scheme = is_string($url) ? strtolower((string) parse_url($url, PHP_URL_SCHEME)) : '';

        if ($url !== null && (filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array($scheme, ['http', 'https'], true))) {
            $errors[$path] = 'La URL debe ser valida y usar http o https.';
        }

        return $url;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normalizeRut(mixed $value, string $path, array &$errors): ?string
    {
        if (! is_string($value) || ! ChileanRut::isValid($value)) {
            $errors[$path] = 'El RUT no es valido.';

            return null;
        }

        return ChileanRut::normalize($value);
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normalizeNumber(FormField $field, mixed $value, string $path, array &$errors): int|float|null
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            $errors[$path] = 'El valor debe ser numerico.';

            return null;
        }

        $number = $value + 0;
        $rules = $field->validation_rules ?? [];

        if (isset($rules['min']) && $number < $rules['min']) {
            $errors[$path] = 'El valor es menor que el minimo permitido.';
        }

        if (isset($rules['max']) && $number > $rules['max']) {
            $errors[$path] = 'El valor supera el maximo permitido.';
        }

        $decimals = (int) ($rules['decimals'] ?? 8);
        if (! $this->hasAllowedDecimals((string) $value, $decimals)) {
            $errors[$path] = 'El valor tiene mas decimales de los permitidos.';
        }

        return $number;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normalizeDate(mixed $value, string $path, array &$errors): ?string
    {
        if (! is_string($value) || ! $this->isDate($value)) {
            $errors[$path] = 'La fecha debe usar formato YYYY-MM-DD.';

            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normalizeTime(mixed $value, string $path, array &$errors): ?string
    {
        if (! is_string($value) || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value)) {
            $errors[$path] = 'La hora debe usar formato HH:mm.';

            return null;
        }

        return strlen($value) === 5 ? $value.':00' : $value;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normalizeDatetime(mixed $value, string $path, array &$errors): ?string
    {
        if (! is_string($value) || strtotime($value) === false) {
            $errors[$path] = 'La fecha y hora no es valida.';

            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normalizeSingleOption(FormField $field, mixed $value, string $path, array &$errors): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            $errors[$path] = 'La opcion seleccionada no es valida.';

            return null;
        }

        $option = (string) $value;
        if (! in_array($option, $this->activeOptionValues($field), true)) {
            $errors[$path] = 'La opcion seleccionada no esta disponible.';
        }

        return $option;
    }

    /**
     * @param  array<string, string>  $errors
     * @return list<string>
     */
    private function normalizeMultipleOptions(FormField $field, mixed $value, string $path, array &$errors): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $errors[$path] = 'El valor debe ser una lista de opciones.';

            return [];
        }

        $allowed = $this->activeOptionValues($field);
        $values = array_values(array_unique(array_map('strval', $value)));

        foreach ($values as $option) {
            if (! in_array($option, $allowed, true)) {
                $errors[$path] = 'Una o mas opciones no estan disponibles.';
                break;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normalizeBoolean(mixed $value, string $path, array &$errors): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [0, 1, '0', '1'], true)) {
            return (bool) $value;
        }

        $errors[$path] = 'El valor debe ser verdadero o falso.';

        return null;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normalizeIntegerRange(mixed $value, int $min, int $max, string $path, array &$errors): ?int
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/^-?\d+$/', $value))) {
            $errors[$path] = 'El valor debe ser un entero.';

            return null;
        }

        $number = (int) $value;
        if ($number < $min || $number > $max) {
            $errors[$path] = 'El valor esta fuera del rango permitido.';
        }

        return $number;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normalizeScale(FormField $field, mixed $value, string $path, array &$errors): ?int
    {
        $settings = $field->settings ?? [];
        $min = (int) ($settings['min'] ?? 1);
        $step = (int) ($settings['step'] ?? 1);
        $number = $this->normalizeIntegerRange($value, $min, (int) ($settings['max'] ?? 10), $path, $errors);

        if ($number !== null && (($number - $min) % $step) !== 0) {
            $errors[$path] = 'El valor no respeta el paso configurado.';
        }

        return $number;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normalizeSlider(FormField $field, mixed $value, string $path, array &$errors): int|float|null
    {
        $settings = $field->settings ?? [];
        $number = $this->normalizeNumberLike($value, $path, $errors);

        if ($number === null) {
            return null;
        }

        $min = (float) ($settings['min'] ?? 0);
        $max = (float) ($settings['max'] ?? 100);
        $step = (float) ($settings['step'] ?? 1);

        if ($number < $min || $number > $max) {
            $errors[$path] = 'El valor esta fuera del rango permitido.';
        }

        if (! $this->fitsStep((float) $number, $min, $step)) {
            $errors[$path] = 'El valor no respeta el paso configurado.';
        }

        return $number;
    }

    /**
     * @param  array<string, string>  $errors
     * @return array{start: string, end: string}|null
     */
    private function normalizeDateRange(FormField $field, mixed $value, string $path, array &$errors): ?array
    {
        if (! is_array($value)) {
            $errors[$path] = 'El rango debe incluir fecha de inicio y termino.';

            return null;
        }

        $start = $value['start'] ?? null;
        $end = $value['end'] ?? null;
        $settings = $field->settings ?? [];

        if (! is_string($start) || ! $this->isDate($start) || ! is_string($end) || ! $this->isDate($end)) {
            $errors[$path] = 'El rango debe usar fechas YYYY-MM-DD.';

            return null;
        }

        if ($start > $end || (($settings['allow_same_day'] ?? true) === false && $start === $end)) {
            $errors[$path] = 'La fecha de inicio debe ser anterior a la fecha de termino.';
        }

        if (isset($settings['min_date']) && $start < $settings['min_date']) {
            $errors[$path] = 'La fecha de inicio es menor que el minimo permitido.';
        }

        if (isset($settings['max_date']) && $end > $settings['max_date']) {
            $errors[$path] = 'La fecha de termino supera el maximo permitido.';
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @param  array<string, string>  $errors
     * @return list<array{name: string, size: int, extension: string, reference: string|null}>
     */
    private function normalizeFileMetadata(FormField $field, mixed $value, string $path, array &$errors): array
    {
        if (is_array($value) && ! array_is_list($value) && isset($value['name'])) {
            $value = [$value];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            $errors[$path] = 'Los archivos deben enviarse como lista de metadata.';

            return [];
        }

        $settings = $field->settings ?? [];
        $maxFiles = (int) ($settings['max_files'] ?? 1);
        $maxBytes = (int) ($settings['max_size_mb'] ?? 10) * 1024 * 1024;
        $allowedExtensions = array_map('strtolower', $settings['allowed_extensions'] ?? ['pdf', 'jpg', 'jpeg', 'png']);

        if (count($value) > $maxFiles) {
            $errors[$path] = 'La cantidad de archivos supera el maximo permitido.';
        }

        $files = [];
        foreach ($value as $index => $file) {
            if (! is_array($file)) {
                $errors["{$path}.{$index}"] = 'La metadata del archivo no es valida.';

                continue;
            }

            $name = (string) ($file['name'] ?? '');
            $size = (int) ($file['size'] ?? 0);
            $extension = strtolower((string) ($file['extension'] ?? pathinfo($name, PATHINFO_EXTENSION)));

            if ($name === '' || mb_strlen($name) > 255 || $size < 0 || $size > $maxBytes || ! in_array($extension, $allowedExtensions, true)) {
                $errors["{$path}.{$index}"] = 'El archivo no cumple la configuracion permitida.';

                continue;
            }

            $files[] = [
                'name' => $name,
                'size' => $size,
                'extension' => $extension,
                'reference' => isset($file['reference']) && is_string($file['reference']) ? mb_substr($file['reference'], 0, 500) : null,
            ];
        }

        return $files;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normalizeSignature(mixed $value, string $path, array &$errors): mixed
    {
        if (is_string($value) && strlen($value) <= 2_000_000) {
            return $value;
        }

        if (is_array($value) && is_string($value['data_url'] ?? null) && strlen($value['data_url']) <= 2_000_000) {
            return ['data_url' => $value['data_url']];
        }

        $errors[$path] = 'La firma no es valida.';

        return null;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normalizeRichText(FormField $field, mixed $value, string $path, array &$errors): mixed
    {
        $rules = $field->validation_rules ?? [];

        if (is_string($value)) {
            $sanitized = (new SanitizedRichText)->sanitize($value);
            $plainLength = mb_strlen(trim(strip_tags($sanitized ?? '')));
            $this->validateRichTextLength($plainLength, $rules, $path, $errors);

            return $sanitized;
        }

        if (! is_array($value) || ! is_array($value['ops'] ?? null) || count($value['ops']) > 2000) {
            $errors[$path] = 'El texto enriquecido debe enviarse como Delta valido.';

            return null;
        }

        $plainLength = 0;
        $ops = [];
        foreach ($value['ops'] as $index => $operation) {
            if (! is_array($operation) || ! is_string($operation['insert'] ?? null)) {
                $errors["{$path}.ops.{$index}"] = 'El Delta solo admite inserciones de texto.';

                continue;
            }

            $insert = $operation['insert'];
            $plainLength += mb_strlen($insert);
            $normalized = ['insert' => $insert];
            $attributes = $this->sanitizeRichTextAttributes($operation['attributes'] ?? null);

            if ($attributes !== []) {
                $normalized['attributes'] = $attributes;
            }

            $ops[] = $normalized;
        }

        $this->validateRichTextLength($plainLength, $rules, $path, $errors);

        return ['ops' => $ops];
    }

    /**
     * @param  Collection<int, EloquentCollection<int, FormField>>  $fieldsByParent
     * @param  Collection<int, FormField>  $fieldsById
     * @param  array<string, string>  $errors
     * @return list<array<string, mixed>>
     */
    private function normalizeRows(
        FormField $field,
        mixed $value,
        Collection $fieldsByParent,
        Collection $fieldsById,
        bool $submit,
        string $path,
        array &$errors,
    ): array {
        if (! is_array($value) || ! array_is_list($value)) {
            $errors[$path] = 'El valor debe ser una lista de filas.';

            return [];
        }

        $settings = $field->settings ?? [];
        $code = (string) $field->fieldType->code;
        $min = (int) ($code === 'table' ? ($settings['min_rows'] ?? 0) : ($settings['min_items'] ?? 0));
        $max = (int) ($code === 'table' ? ($settings['max_rows'] ?? 1000) : ($settings['max_items'] ?? 100));

        if ($submit && count($value) < $min) {
            $errors[$path] = 'La cantidad de filas es menor que el minimo permitido.';
        }

        if (count($value) > $max) {
            $errors[$path] = 'La cantidad de filas supera el maximo permitido.';
        }

        $children = $this->childrenFor($field, $fieldsByParent);
        $rows = [];
        foreach ($value as $rowIndex => $row) {
            if (! is_array($row)) {
                $errors["{$path}.{$rowIndex}"] = 'La fila no es valida.';

                continue;
            }

            $rows[] = $this->normalizeRecord($children, $row, $fieldsByParent, $fieldsById, $submit, "{$path}.{$rowIndex}", $errors);
        }

        return $rows;
    }

    /**
     * @param  iterable<FormField>  $fields
     * @param  array<string, mixed>  $record
     * @param  Collection<int, EloquentCollection<int, FormField>>  $fieldsByParent
     * @param  Collection<int, FormField>  $fieldsById
     * @param  array<string, string>  $errors
     * @return array<string, mixed>
     */
    private function normalizeRecord(
        iterable $fields,
        array $record,
        Collection $fieldsByParent,
        Collection $fieldsById,
        bool $submit,
        string $path,
        array &$errors,
    ): array {
        $normalized = [];

        foreach ($fields as $field) {
            if (! $this->visibilityResolver->isVisible($field)) {
                continue;
            }

            $code = (string) $field->fieldType->code;
            if ($code === 'section') {
                $normalized += $this->normalizeRecord(
                    $this->childrenFor($field, $fieldsByParent),
                    $record,
                    $fieldsByParent,
                    $fieldsById,
                    $submit,
                    $path,
                    $errors,
                );

                continue;
            }

            if (in_array($code, ['paragraph', 'divider'], true)) {
                continue;
            }

            $fieldPath = "{$path}.{$field->field_key}";
            $hasValue = array_key_exists($field->field_key, $record);
            if (! $hasValue && (! $submit || ! $field->is_required)) {
                continue;
            }

            $errorCount = count($errors);
            $value = $this->normalizeField(
                $field,
                $hasValue ? $record[$field->field_key] : null,
                $fieldsByParent,
                $fieldsById,
                $submit,
                $fieldPath,
                $errors,
            );

            if (count($errors) === $errorCount) {
                $normalized[$field->field_key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  EloquentCollection<int, FormField>  $fields
     * @param  Collection<int, FormField>  $fieldsById
     * @return list<FormField>
     */
    private function responseableTopLevelFields(EloquentCollection $fields, Collection $fieldsById): array
    {
        return $fields
            ->filter(fn (FormField $field): bool => $this->visibilityResolver->isResponseable($field)
                && ! $this->isInsideContainerAnswer($field, $fieldsById))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, EloquentCollection<int, FormField>>  $fieldsByParent
     * @return list<FormField>
     */
    private function childrenFor(FormField $field, Collection $fieldsByParent): array
    {
        return ($fieldsByParent->get($field->id) ?? collect())
            ->filter(fn (FormField $child): bool => $this->visibilityResolver->isVisible($child))
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, FormField>  $fieldsById
     */
    private function isInsideContainerAnswer(FormField $field, Collection $fieldsById): bool
    {
        $parentId = $field->parent_field_id;

        while ($parentId !== null) {
            $parent = $fieldsById->get($parentId);
            if ($parent === null) {
                return false;
            }

            if ($this->visibilityResolver->isContainerAnswer($parent)) {
                return true;
            }

            $parentId = $parent->parent_field_id;
        }

        return false;
    }

    /** @return list<string> */
    private function activeOptionValues(FormField $field): array
    {
        return $field->options
            ->filter(fn (FormFieldOption $option): bool => $option->is_active)
            ->pluck('option_value')
            ->map(fn (mixed $value): string => (string) $value)
            ->values()
            ->all();
    }

    private function isEmpty(mixed $value, string $code): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            if ($code === 'rich_text' && is_array($value['ops'] ?? null)) {
                $text = collect($value['ops'])
                    ->map(fn (mixed $operation): string => is_array($operation) ? (string) ($operation['insert'] ?? '') : '')
                    ->implode('');

                return trim($text) === '';
            }

            return $value === [];
        }

        return false;
    }

    private function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function hasAllowedDecimals(string $value, int $decimals): bool
    {
        if (! str_contains($value, '.')) {
            return true;
        }

        return strlen(rtrim(substr($value, (int) strpos($value, '.') + 1), '0')) <= $decimals;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function normalizeNumberLike(mixed $value, string $path, array &$errors): int|float|null
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            $errors[$path] = 'El valor debe ser numerico.';

            return null;
        }

        return $value + 0;
    }

    private function fitsStep(float $value, float $min, float $step): bool
    {
        if ($step <= 0) {
            return false;
        }

        $steps = ($value - $min) / $step;

        return abs($steps - round($steps)) < 0.000001;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $errors
     */
    private function validateRichTextLength(int $plainLength, array $rules, string $path, array &$errors): void
    {
        if (isset($rules['min_length']) && $plainLength < (int) $rules['min_length']) {
            $errors[$path] = 'El texto enriquecido no alcanza el largo minimo.';
        }

        if (isset($rules['max_length']) && $plainLength > (int) $rules['max_length']) {
            $errors[$path] = 'El texto enriquecido supera el largo maximo.';
        }
    }

    /** @return array<string, bool|int|string> */
    private function sanitizeRichTextAttributes(mixed $attributes): array
    {
        if (! is_array($attributes)) {
            return [];
        }

        $safe = [];
        foreach (['bold', 'italic', 'underline', 'strike'] as $format) {
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

    /**
     * @param  array<string, string>  $errors
     */
    private function rejectStructural(string $path, array &$errors): null
    {
        $errors[$path] = 'Este campo no admite respuesta.';

        return null;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function rejectUnsupported(string $path, array &$errors): null
    {
        $errors[$path] = 'El tipo de campo no esta soportado para respuestas.';

        return null;
    }
}
