<?php

namespace App\Services;

use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormTypeVersion;
use App\Models\User;
use App\Services\FormBuilder\FieldTypes\FieldTypeStrategyRegistry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FormDefinitionSaveService
{
    public function __construct(
        private readonly FormFieldService $fieldService,
        private readonly FieldTypeStrategyRegistry $strategyRegistry,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    public function save(FormTypeVersion $version, array $fields, User $actor): FormTypeVersion
    {
        return DB::transaction(function () use ($version, $fields, $actor): FormTypeVersion {
            $version = FormTypeVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $this->fieldService->ensureDraft($version);

            $existingFields = FormField::query()
                ->where('form_type_version_id', $version->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $existingOptions = FormFieldOption::query()
                ->whereIn('form_field_id', $existingFields->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $fieldTypes = FieldType::query()
                ->whereIn('id', collect($fields)->pluck('field_type_id')->unique())
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            $this->validateDefinition($fields, $fieldTypes, $existingFields, $existingOptions);
            $this->releaseUniqueValues($existingFields->values()->all(), $existingOptions->values()->all());

            $fieldsByParent = [];

            foreach ($fields as $field) {
                $parentKey = $field['parent_client_id'] === null
                    ? '__root__'
                    : 'parent:'.$field['parent_client_id'];
                $fieldsByParent[$parentKey][] = $field;
            }

            $savedFieldIds = [];
            $savedOptionIds = [];

            $persistChildren = function (?string $parentClientId, ?FormField $parent) use (
                &$persistChildren,
                &$savedFieldIds,
                &$savedOptionIds,
                $fieldsByParent,
                $existingFields,
                $existingOptions,
                $fieldTypes,
                $version,
                $actor,
            ): void {
                $parentKey = $parentClientId === null ? '__root__' : 'parent:'.$parentClientId;
                $children = $fieldsByParent[$parentKey] ?? [];

                usort($children, fn (array $left, array $right): int => $left['sort_order'] <=> $right['sort_order']);

                foreach ($children as $fieldData) {
                    $fieldType = $fieldTypes->get((int) $fieldData['field_type_id']);
                    $configuration = $this->strategyRegistry->validateAndNormalize(
                        $fieldType->code,
                        $fieldData['settings'],
                        $fieldData['validation_rules'],
                        $fieldData['default_value'],
                    );
                    $field = isset($fieldData['id'])
                        ? $existingFields->get((int) $fieldData['id'])
                        : new FormField;

                    $field->forceFill([
                        'form_type_version_id' => $version->id,
                        'field_type_id' => $fieldType->id,
                        'parent_field_id' => $parent?->id,
                        'field_key' => $fieldData['field_key'],
                        'label' => $fieldData['label'],
                        'description' => $fieldData['description'],
                        'placeholder' => $fieldData['placeholder'],
                        'is_required' => $fieldData['is_required'],
                        'is_readonly' => $fieldData['is_readonly'],
                        'is_hidden' => $fieldData['is_hidden'],
                        'is_active' => $fieldData['is_active'],
                        'sort_order' => $fieldData['sort_order'],
                        'width' => $fieldType->code === 'table' ? 12 : $fieldData['width'],
                        ...$configuration,
                        'created_by' => $field->exists ? $field->created_by : $actor->id,
                        'updated_by' => $actor->id,
                    ])->save();

                    $savedFieldIds[] = $field->id;

                    foreach ($fieldData['options'] as $optionData) {
                        $option = isset($optionData['id'])
                            ? $existingOptions->get((int) $optionData['id'])
                            : new FormFieldOption;

                        $option->forceFill([
                            'form_field_id' => $field->id,
                            'option_value' => $optionData['option_value'],
                            'option_label' => $optionData['option_label'],
                            'sort_order' => $optionData['sort_order'],
                            'is_default' => $optionData['is_default'],
                            'is_active' => $optionData['is_active'],
                            'settings' => $optionData['settings'],
                            'created_by' => $option->exists ? $option->created_by : $actor->id,
                            'updated_by' => $actor->id,
                        ])->save();

                        $savedOptionIds[] = $option->id;
                    }

                    $persistChildren($fieldData['client_id'], $field);
                }
            };

            $persistChildren(null, null);

            $staleOptionIds = $existingOptions->keys()->diff($savedOptionIds);

            if ($staleOptionIds->isNotEmpty()) {
                FormFieldOption::query()->whereKey($staleOptionIds->all())->update(['updated_by' => $actor->id]);
                FormFieldOption::query()->whereKey($staleOptionIds->all())->delete();
            }

            $staleFieldIds = $existingFields->keys()->diff($savedFieldIds);

            if ($staleFieldIds->isNotEmpty()) {
                FormField::query()->whereKey($staleFieldIds->all())->update(['updated_by' => $actor->id]);
                FormField::query()->whereKey($staleFieldIds->all())->delete();
            }

            $version->forceFill(['updated_by' => $actor->id]);
            $version->touch();

            return $version->refresh();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  EloquentCollection<int, FieldType>  $fieldTypes
     * @param  EloquentCollection<int, FormField>  $existingFields
     * @param  EloquentCollection<int, FormFieldOption>  $existingOptions
     */
    private function validateDefinition(
        array $fields,
        EloquentCollection $fieldTypes,
        EloquentCollection $existingFields,
        EloquentCollection $existingOptions,
    ): void {
        $byClientId = collect($fields)->keyBy('client_id');
        $usedKeys = [];
        $usedPositions = [];

        foreach ($fields as $index => $field) {
            $clientId = (string) $field['client_id'];
            $parentClientId = $field['parent_client_id'];
            $fieldType = $fieldTypes->get((int) $field['field_type_id']);

            if ($fieldType === null) {
                throw ValidationException::withMessages([
                    "fields.{$index}.field_type_id" => 'El tipo de campo no existe o esta inactivo.',
                ]);
            }

            if (isset($field['id']) && ! $existingFields->has((int) $field['id'])) {
                throw ValidationException::withMessages([
                    "fields.{$index}.id" => 'El campo no pertenece a esta version.',
                ]);
            }

            if (isset($usedKeys[$field['field_key']])) {
                throw ValidationException::withMessages([
                    "fields.{$index}.field_key" => 'El identificador del campo debe ser unico.',
                ]);
            }

            $usedKeys[$field['field_key']] = true;
            $positionKey = ($parentClientId ?? '__root__').':'.$field['sort_order'];

            if (isset($usedPositions[$positionKey])) {
                throw ValidationException::withMessages([
                    "fields.{$index}.sort_order" => 'Dos campos hermanos no pueden compartir la misma posicion.',
                ]);
            }

            $usedPositions[$positionKey] = true;

            if ($parentClientId !== null) {
                $parentData = $byClientId->get($parentClientId);

                if ($parentData === null || $parentClientId === $clientId) {
                    throw ValidationException::withMessages([
                        "fields.{$index}.parent_client_id" => 'El campo padre no es valido.',
                    ]);
                }

                $parentType = $fieldTypes->get((int) $parentData['field_type_id']);

                if ($parentType === null || ! $parentType->is_container) {
                    throw ValidationException::withMessages([
                        "fields.{$index}.parent_client_id" => 'El padre debe ser un campo contenedor.',
                    ]);
                }

                if ($parentType->code === 'table' && $fieldType->is_container) {
                    throw ValidationException::withMessages([
                        "fields.{$index}.parent_client_id" => 'Las columnas de una tabla no pueden ser contenedores.',
                    ]);
                }
            }

            $this->validateOptions($field, $index, $fieldType, $existingFields, $existingOptions);
            $this->assertReachableRoot($clientId, $byClientId, $index);
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  EloquentCollection<int, FormField>  $existingFields
     * @param  EloquentCollection<int, FormFieldOption>  $existingOptions
     */
    private function validateOptions(
        array $field,
        int $fieldIndex,
        FieldType $fieldType,
        EloquentCollection $existingFields,
        EloquentCollection $existingOptions,
    ): void {
        if (! $fieldType->has_options && $field['options'] !== []) {
            throw ValidationException::withMessages([
                "fields.{$fieldIndex}.options" => 'El tipo de campo no admite opciones.',
            ]);
        }

        $values = [];
        $positions = [];
        $defaultCount = 0;

        foreach ($field['options'] as $optionIndex => $option) {
            if (isset($option['id'])) {
                $existingOption = $existingOptions->get((int) $option['id']);
                $existingField = isset($field['id']) ? $existingFields->get((int) $field['id']) : null;

                if ($existingOption === null || $existingField === null || $existingOption->form_field_id !== $existingField->id) {
                    throw ValidationException::withMessages([
                        "fields.{$fieldIndex}.options.{$optionIndex}.id" => 'La opcion no pertenece a este campo.',
                    ]);
                }
            }

            if (isset($values[$option['option_value']])) {
                throw ValidationException::withMessages([
                    "fields.{$fieldIndex}.options.{$optionIndex}.option_value" => 'El valor de la opcion debe ser unico.',
                ]);
            }

            if (isset($positions[$option['sort_order']])) {
                throw ValidationException::withMessages([
                    "fields.{$fieldIndex}.options.{$optionIndex}.sort_order" => 'Dos opciones no pueden compartir la misma posicion.',
                ]);
            }

            $values[$option['option_value']] = true;
            $positions[$option['sort_order']] = true;
            $defaultCount += $option['is_default'] ? 1 : 0;
        }

        if (in_array($fieldType->code, ['select', 'radio'], true) && $defaultCount > 1) {
            throw ValidationException::withMessages([
                "fields.{$fieldIndex}.options" => 'El campo solo puede tener una opcion predeterminada.',
            ]);
        }
    }

    /** @param Collection<string, array<string, mixed>> $byClientId */
    private function assertReachableRoot(string $clientId, Collection $byClientId, int $fieldIndex): void
    {
        $seen = [];
        $currentId = $clientId;
        $depth = 0;

        while ($currentId !== null) {
            if (isset($seen[$currentId])) {
                throw ValidationException::withMessages([
                    "fields.{$fieldIndex}.parent_client_id" => 'La jerarquia de campos contiene una referencia circular.',
                ]);
            }

            if ($depth >= 20) {
                throw ValidationException::withMessages([
                    "fields.{$fieldIndex}.parent_client_id" => 'La jerarquia no puede superar 20 niveles.',
                ]);
            }

            $seen[$currentId] = true;
            $currentId = $byClientId->get($currentId)['parent_client_id'] ?? null;
            $depth += 1;
        }
    }

    /**
     * @param  list<FormField>  $fields
     * @param  list<FormFieldOption>  $options
     */
    private function releaseUniqueValues(array $fields, array $options): void
    {
        $prefix = '__pending__'.Str::uuid()->toString().'__';

        foreach ($fields as $field) {
            $field->forceFill(['field_key' => $prefix.$field->id])->saveQuietly();
        }

        foreach ($options as $option) {
            $option->forceFill(['option_value' => $prefix.$option->id])->saveQuietly();
        }
    }
}
