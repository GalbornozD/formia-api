<?php

namespace App\Services;

use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormTypeVersion;
use App\Models\User;
use App\Services\FormBuilder\FieldTypes\FieldTypeStrategyRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FormFieldService
{
    public function __construct(private readonly FieldTypeStrategyRegistry $strategyRegistry) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(FormTypeVersion $version, array $data, User $actor): FormField
    {
        return DB::transaction(function () use ($version, $data, $actor): FormField {
            $version = $this->lockDraft($version);
            $fieldType = $this->activeFieldType((int) $data['field_type_id']);
            $parent = $this->parentFor($version, $data['parent_field_id'] ?? null);
            $this->validateParent($version, $parent, $fieldType);
            $this->ensureUniqueKey($version, (string) $data['field_key']);

            $configuration = $this->strategyRegistry->validateAndNormalize(
                $fieldType->code,
                $data['settings'] ?? [],
                $data['validation_rules'] ?? [],
                $data['default_value'] ?? null,
            );
            $sortOrder = array_key_exists('sort_order', $data)
                ? (int) $data['sort_order']
                : $this->nextSortOrder($version, $parent?->id);

            if (array_key_exists('sort_order', $data)) {
                $this->makeSpaceAt($version, $parent?->id, $sortOrder, $actor);
            }

            $field = new FormField;
            $field->forceFill([
                'form_type_version_id' => $version->id,
                'field_type_id' => $fieldType->id,
                'parent_field_id' => $parent?->id,
                'field_key' => $data['field_key'],
                'label' => $data['label'],
                'description' => $data['description'] ?? null,
                'placeholder' => $data['placeholder'] ?? null,
                'is_required' => $data['is_required'] ?? false,
                'is_readonly' => $data['is_readonly'] ?? false,
                'is_hidden' => $data['is_hidden'] ?? false,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $sortOrder,
                'width' => $this->normalizedWidth($fieldType, $data['width'] ?? 12),
                ...$configuration,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            return $field->load(['fieldType', 'options', 'children']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FormField $field, array $data, User $actor): FormField
    {
        return DB::transaction(function () use ($field, $data, $actor): FormField {
            $version = $this->lockDraft($field->formTypeVersion);
            $field = FormField::query()->whereKey($field->id)->lockForUpdate()->firstOrFail();
            $this->assertFieldVersion($field, $version);

            $typeChanged = array_key_exists('field_type_id', $data)
                && (int) $data['field_type_id'] !== $field->field_type_id;
            $fieldType = $typeChanged
                ? $this->activeFieldType((int) $data['field_type_id'])
                : $field->fieldType;

            $parentId = array_key_exists('parent_field_id', $data)
                ? $data['parent_field_id']
                : $field->parent_field_id;
            $parent = $this->parentFor($version, $parentId);
            $this->validateParent($version, $parent, $fieldType, $field);

            if (array_key_exists('field_key', $data) && $data['field_key'] !== $field->field_key) {
                $this->ensureUniqueKey($version, (string) $data['field_key'], $field->id);
            }

            if (! $fieldType->has_options && $field->options()->exists()) {
                throw ValidationException::withMessages([
                    'field_type_id' => 'El campo tiene opciones y no puede cambiarse a un tipo sin opciones.',
                ]);
            }

            if (! $fieldType->is_container && $field->children()->exists()) {
                throw ValidationException::withMessages([
                    'field_type_id' => 'El campo tiene hijos y no puede cambiarse a un tipo que no sea contenedor.',
                ]);
            }

            $settings = array_key_exists('settings', $data)
                ? $data['settings']
                : ($typeChanged ? [] : ($field->settings ?? []));
            $validationRules = array_key_exists('validation_rules', $data)
                ? $data['validation_rules']
                : ($typeChanged ? [] : ($field->validation_rules ?? []));
            $configuration = $this->strategyRegistry->validateAndNormalize(
                $fieldType->code,
                $settings,
                $validationRules,
                array_key_exists('default_value', $data)
                    ? $data['default_value']
                    : ($typeChanged ? null : $field->default_value),
            );

            $editable = [
                'field_key', 'label', 'description', 'placeholder',
                'is_required', 'is_readonly', 'is_hidden', 'is_active', 'sort_order', 'width',
            ];
            $changes = [];

            foreach ($editable as $attribute) {
                if (array_key_exists($attribute, $data)) {
                    $changes[$attribute] = $data[$attribute];
                }
            }

            $changes['width'] = $this->normalizedWidth(
                $fieldType,
                $data['width'] ?? $field->width,
            );

            $field->forceFill([
                ...$changes,
                'field_type_id' => $fieldType->id,
                'parent_field_id' => $parent?->id,
                ...$configuration,
                'updated_by' => $actor->id,
            ])->save();

            return $field->refresh()->load(['fieldType', 'options', 'children']);
        });
    }

    public function delete(FormField $field, User $actor): void
    {
        DB::transaction(function () use ($field, $actor): void {
            $version = $this->lockDraft($field->formTypeVersion);
            $field = FormField::query()->whereKey($field->id)->lockForUpdate()->firstOrFail();
            $this->assertFieldVersion($field, $version);
            $field->forceFill(['updated_by' => $actor->id])->save();
            $field->delete();
        });
    }

    /**
     * @param  array{parent_field_id?: int|null, sort_order?: int}  $data
     */
    public function duplicate(FormField $source, array $data, User $actor): FormField
    {
        return DB::transaction(function () use ($source, $data, $actor): FormField {
            $version = $this->lockDraft($source->formTypeVersion);
            $fields = FormField::query()
                ->where('form_type_version_id', $version->id)
                ->with(['fieldType', 'options'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $source = $fields->firstWhere('id', $source->id);

            if ($source === null) {
                abort(404);
            }

            $targetParent = $this->parentFor(
                $version,
                array_key_exists('parent_field_id', $data) ? $data['parent_field_id'] : $source->parent_field_id,
            );
            $this->validateParent($version, $targetParent, $source->fieldType);
            $targetSortOrder = $data['sort_order'] ?? ($source->sort_order + 1);
            $this->makeSpaceAt($version, $targetParent?->id, $targetSortOrder, $actor);

            $childrenByParent = $fields->groupBy(fn (FormField $item): int => $item->parent_field_id ?? 0);
            $usedKeys = $fields->pluck('field_key')->all();
            $duplicate = $this->copySubtree(
                $source,
                $version,
                $targetParent?->id,
                $targetSortOrder,
                $childrenByParent,
                $usedKeys,
                $actor,
            );

            return $duplicate->load(['fieldType', 'options', 'children']);
        });
    }

    /**
     * @param  list<array{id: int, parent_field_id: int|null, sort_order: int}>  $items
     */
    public function reorder(FormTypeVersion $version, array $items, User $actor): void
    {
        DB::transaction(function () use ($version, $items, $actor): void {
            $version = $this->lockDraft($version);
            $fields = FormField::query()
                ->where('form_type_version_id', $version->id)
                ->with('fieldType')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $submittedIds = collect($items)->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
            $persistedIds = $fields->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all();

            if ($submittedIds !== $persistedIds) {
                throw ValidationException::withMessages([
                    'fields' => 'El orden debe incluir exactamente todos los campos de la versión.',
                ]);
            }

            $parents = [];
            $positions = [];

            foreach ($items as $index => $item) {
                $field = $fields[(int) $item['id']];
                $parentId = $item['parent_field_id'] === null ? null : (int) $item['parent_field_id'];
                $parent = $parentId === null ? null : $fields->get($parentId);

                if ($parentId === $field->id || ($parentId !== null && $parent === null)) {
                    throw ValidationException::withMessages(["fields.{$index}.parent_field_id" => 'El campo padre no es válido.']);
                }

                $this->validateParent($version, $parent, $field->fieldType, $field, skipCycleCheck: true);
                $positionKey = ($parentId ?? 'root').':'.$item['sort_order'];

                if (isset($positions[$positionKey])) {
                    throw ValidationException::withMessages(["fields.{$index}.sort_order" => 'Dos campos hermanos no pueden compartir la misma posición.']);
                }

                $positions[$positionKey] = true;
                $parents[$field->id] = $parentId;
            }

            $this->assertAcyclic($parents);

            foreach ($items as $item) {
                $fields[(int) $item['id']]->forceFill([
                    'parent_field_id' => $item['parent_field_id'],
                    'sort_order' => $item['sort_order'],
                    'updated_by' => $actor->id,
                ])->save();
            }
        });
    }

    public function validateForPublishing(FormTypeVersion $version): void
    {
        $fields = FormField::query()
            ->where('form_type_version_id', $version->id)
            ->with(['fieldType', 'options'])
            ->get();

        if ($fields->isEmpty()) {
            throw ValidationException::withMessages(['fields' => 'El formulario debe tener al menos un campo antes de publicarse.']);
        }

        $byId = $fields->keyBy('id');
        $parents = [];

        foreach ($fields as $field) {
            if (! $field->fieldType->is_active) {
                throw ValidationException::withMessages([
                    "fields.{$field->id}.field_type_id" => "El tipo del campo '{$field->label}' está inactivo.",
                ]);
            }

            $this->strategyRegistry->validateAndNormalize(
                $field->fieldType->code,
                $field->settings ?? [],
                $field->validation_rules ?? [],
                $field->default_value,
            );

            $parent = $field->parent_field_id === null ? null : $byId->get($field->parent_field_id);
            $this->validateParent($version, $parent, $field->fieldType, $field, skipCycleCheck: true);
            $parents[$field->id] = $field->parent_field_id;

            if (! $field->fieldType->has_options && $field->options->isNotEmpty()) {
                throw ValidationException::withMessages([
                    "fields.{$field->id}.options" => "El campo '{$field->label}' no admite opciones.",
                ]);
            }

            if ($field->fieldType->has_options && $field->options->where('is_active', true)->isEmpty()) {
                throw ValidationException::withMessages([
                    "fields.{$field->id}.options" => "El campo '{$field->label}' necesita al menos una opción activa.",
                ]);
            }

            if ($field->fieldType->code === 'table'
                && $fields->where('parent_field_id', $field->id)->where('is_active', true)->isEmpty()) {
                throw ValidationException::withMessages([
                    "fields.{$field->id}.children" => "La tabla '{$field->label}' necesita al menos una columna activa.",
                ]);
            }

            if (in_array($field->fieldType->code, ['select', 'radio'], true)
                && $field->options->where('is_default', true)->count() > 1) {
                throw ValidationException::withMessages([
                    "fields.{$field->id}.options" => "El campo '{$field->label}' solo puede tener una opción predeterminada.",
                ]);
            }
        }

        $this->assertAcyclic($parents);
    }

    public function ensureDraft(FormTypeVersion $version): void
    {
        if ($version->is_published) {
            abort(409, 'Una versión publicada es estructuralmente inmutable. Crea una nueva versión para modificarla.');
        }

        if (! $version->is_active) {
            abort(409, 'La versión no está activa.');
        }
    }

    private function lockDraft(FormTypeVersion $version): FormTypeVersion
    {
        $version = FormTypeVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
        $this->ensureDraft($version);

        return $version;
    }

    private function activeFieldType(int $fieldTypeId): FieldType
    {
        $fieldType = FieldType::query()->whereKey($fieldTypeId)->where('is_active', true)->first();

        if ($fieldType === null) {
            throw ValidationException::withMessages(['field_type_id' => 'El tipo de campo no existe o está inactivo.']);
        }

        return $fieldType;
    }

    private function parentFor(FormTypeVersion $version, mixed $parentId): ?FormField
    {
        if ($parentId === null) {
            return null;
        }

        $parent = FormField::query()->with('fieldType')->find((int) $parentId);

        if ($parent === null || $parent->form_type_version_id !== $version->id) {
            throw ValidationException::withMessages(['parent_field_id' => 'El campo padre no pertenece a esta versión.']);
        }

        return $parent;
    }

    private function validateParent(
        FormTypeVersion $version,
        ?FormField $parent,
        FieldType $childType,
        ?FormField $field = null,
        bool $skipCycleCheck = false,
    ): void {
        if ($parent === null) {
            return;
        }

        if ($parent->form_type_version_id !== $version->id || ! $parent->fieldType->is_container) {
            throw ValidationException::withMessages(['parent_field_id' => 'El padre debe ser un contenedor de la misma versión.']);
        }

        if ($field !== null && $parent->id === $field->id) {
            throw ValidationException::withMessages(['parent_field_id' => 'Un campo no puede ser su propio padre.']);
        }

        if ($parent->fieldType->code === 'table' && $childType->is_container) {
            throw ValidationException::withMessages(['parent_field_id' => 'Las columnas de una tabla no pueden ser contenedores.']);
        }

        if (! $skipCycleCheck && $field !== null && $this->wouldCreateCycle($version, $field->id, $parent->id)) {
            throw ValidationException::withMessages(['parent_field_id' => 'La jerarquía propuesta produciría una referencia circular.']);
        }
    }

    private function wouldCreateCycle(FormTypeVersion $version, int $fieldId, int $parentId): bool
    {
        $parents = FormField::query()
            ->where('form_type_version_id', $version->id)
            ->pluck('parent_field_id', 'id')
            ->all();
        $current = $parentId;

        while ($current !== null) {
            if ((int) $current === $fieldId) {
                return true;
            }

            $current = $parents[$current] ?? null;
        }

        return false;
    }

    /** @param array<int, int|null> $parents */
    private function assertAcyclic(array $parents): void
    {
        foreach (array_keys($parents) as $start) {
            $seen = [];
            $current = $start;

            while ($current !== null) {
                if (isset($seen[$current])) {
                    throw ValidationException::withMessages(['fields' => 'La jerarquía de campos contiene una referencia circular.']);
                }

                $seen[$current] = true;
                $current = $parents[$current] ?? null;
            }
        }
    }

    private function ensureUniqueKey(FormTypeVersion $version, string $fieldKey, ?int $exceptId = null): void
    {
        $exists = FormField::query()
            ->where('form_type_version_id', $version->id)
            ->where('field_key', $fieldKey)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['field_key' => "El identificador '{$fieldKey}' ya existe en esta versión."]);
        }
    }

    private function assertFieldVersion(FormField $field, FormTypeVersion $version): void
    {
        abort_unless($field->form_type_version_id === $version->id, 404);
    }

    private function nextSortOrder(FormTypeVersion $version, ?int $parentId): int
    {
        return (int) FormField::query()
            ->where('form_type_version_id', $version->id)
            ->where('parent_field_id', $parentId)
            ->max('sort_order') + 1;
    }

    private function makeSpaceAt(FormTypeVersion $version, ?int $parentId, int $sortOrder, User $actor): void
    {
        FormField::query()
            ->where('form_type_version_id', $version->id)
            ->where('parent_field_id', $parentId)
            ->where('sort_order', '>=', $sortOrder)
            ->update([
                'sort_order' => DB::raw('sort_order + 1'),
                'updated_by' => $actor->id,
            ]);
    }

    /**
     * @param  Collection<int, FormField>  $fieldsByParent
     * @param  list<string>  $usedKeys
     */
    private function copySubtree(
        FormField $source,
        FormTypeVersion $version,
        ?int $parentId,
        int $sortOrder,
        Collection $fieldsByParent,
        array &$usedKeys,
        User $actor,
    ): FormField {
        $copy = new FormField;
        $copy->forceFill([
            'form_type_version_id' => $version->id,
            'field_type_id' => $source->field_type_id,
            'parent_field_id' => $parentId,
            'field_key' => $this->uniqueCopyKey($source->field_key, $usedKeys),
            'label' => $source->label,
            'description' => $source->description,
            'placeholder' => $source->placeholder,
            'default_value' => $source->default_value,
            'is_required' => $source->is_required,
            'is_readonly' => $source->is_readonly,
            'is_hidden' => $source->is_hidden,
            'is_active' => $source->is_active,
            'sort_order' => $sortOrder,
            'width' => $source->fieldType->code === 'table' ? 12 : $source->width,
            'validation_rules' => $source->validation_rules,
            'settings' => $source->settings,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ])->save();

        foreach ($source->options as $option) {
            $optionCopy = new FormFieldOption;
            $optionCopy->forceFill([
                'form_field_id' => $copy->id,
                'option_value' => $option->option_value,
                'option_label' => $option->option_label,
                'sort_order' => $option->sort_order,
                'is_default' => $option->is_default,
                'is_active' => $option->is_active,
                'settings' => $option->settings,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();
        }

        foreach ($fieldsByParent->get($source->id, collect())->sortBy('sort_order') as $child) {
            $this->copySubtree($child, $version, $copy->id, $child->sort_order, $fieldsByParent, $usedKeys, $actor);
        }

        return $copy;
    }

    private function normalizedWidth(FieldType $fieldType, mixed $width): int
    {
        return $fieldType->code === 'table' ? 12 : (int) $width;
    }

    /** @param list<string> $usedKeys */
    private function uniqueCopyKey(string $fieldKey, array &$usedKeys): string
    {
        $base = substr($fieldKey, 0, 95).'_copy';
        $candidate = $base;
        $suffix = 2;

        while (in_array($candidate, $usedKeys, true)) {
            $candidate = substr($base, 0, 98 - strlen((string) $suffix)).'_'.$suffix;
            $suffix++;
        }

        $usedKeys[] = $candidate;

        return $candidate;
    }
}
