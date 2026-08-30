<?php

namespace App\Services;

use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormTypeVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FormFieldOptionService
{
    public function __construct(private readonly FormFieldService $fieldService) {}

    /** @param array<string, mixed> $data */
    public function create(FormField $field, array $data, User $actor): FormFieldOption
    {
        return DB::transaction(function () use ($field, $data, $actor): FormFieldOption {
            [$version, $field] = $this->lockField($field);
            $this->fieldService->ensureDraft($version);
            $this->ensureSupportsOptions($field);
            $this->ensureUniqueValue($field, (string) $data['option_value']);

            $sortOrder = array_key_exists('sort_order', $data)
                ? (int) $data['sort_order']
                : ((int) $field->options()->max('sort_order') + 1);

            if (($data['is_default'] ?? false) && $this->hasSingleDefault($field)) {
                $field->options()->update(['is_default' => false, 'updated_by' => $actor->id]);
            }

            $option = new FormFieldOption;
            $option->forceFill([
                'form_field_id' => $field->id,
                'option_value' => $data['option_value'],
                'option_label' => $data['option_label'],
                'sort_order' => $sortOrder,
                'is_default' => $data['is_default'] ?? false,
                'is_active' => $data['is_active'] ?? true,
                'settings' => $data['settings'] ?? [],
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            return $option->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function update(FormFieldOption $option, array $data, User $actor): FormFieldOption
    {
        return DB::transaction(function () use ($option, $data, $actor): FormFieldOption {
            [$version, $field] = $this->lockField($option->formField);
            $this->fieldService->ensureDraft($version);
            $option = FormFieldOption::query()->whereKey($option->id)->lockForUpdate()->firstOrFail();
            abort_unless($option->form_field_id === $field->id, 404);
            $this->ensureSupportsOptions($field);

            if (array_key_exists('option_value', $data) && $data['option_value'] !== $option->option_value) {
                $this->ensureUniqueValue($field, (string) $data['option_value'], $option->id);
            }

            if (($data['is_default'] ?? false) && $this->hasSingleDefault($field)) {
                $field->options()->whereKeyNot($option->id)->update([
                    'is_default' => false,
                    'updated_by' => $actor->id,
                ]);
            }

            $changes = [];

            foreach (['option_value', 'option_label', 'sort_order', 'is_default', 'is_active', 'settings'] as $attribute) {
                if (array_key_exists($attribute, $data)) {
                    $changes[$attribute] = $data[$attribute];
                }
            }

            $option->forceFill([...$changes, 'updated_by' => $actor->id])->save();

            return $option->refresh();
        });
    }

    public function delete(FormFieldOption $option, User $actor): void
    {
        DB::transaction(function () use ($option, $actor): void {
            [$version, $field] = $this->lockField($option->formField);
            $this->fieldService->ensureDraft($version);
            $option = FormFieldOption::query()->whereKey($option->id)->lockForUpdate()->firstOrFail();
            abort_unless($option->form_field_id === $field->id, 404);
            $option->forceFill(['updated_by' => $actor->id])->save();
            $option->delete();
        });
    }

    /** @param list<array{id: int, sort_order: int}> $items */
    public function reorder(FormField $field, array $items, User $actor): void
    {
        DB::transaction(function () use ($field, $items, $actor): void {
            [$version, $field] = $this->lockField($field);
            $this->fieldService->ensureDraft($version);
            $this->ensureSupportsOptions($field);
            $options = $field->options()->lockForUpdate()->get()->keyBy('id');
            $submittedIds = collect($items)->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
            $persistedIds = $options->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all();

            if ($submittedIds !== $persistedIds) {
                throw ValidationException::withMessages([
                    'options' => 'El orden debe incluir exactamente todas las opciones del campo.',
                ]);
            }

            if (collect($items)->pluck('sort_order')->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['options' => 'Las opciones no pueden compartir la misma posición.']);
            }

            foreach ($items as $item) {
                $options[(int) $item['id']]->forceFill([
                    'sort_order' => $item['sort_order'],
                    'updated_by' => $actor->id,
                ])->save();
            }
        });
    }

    /** @return array{FormTypeVersion, FormField} */
    private function lockField(FormField $field): array
    {
        $version = FormTypeVersion::query()->whereKey($field->form_type_version_id)->lockForUpdate()->firstOrFail();
        $field = FormField::query()->with('fieldType')->whereKey($field->id)->lockForUpdate()->firstOrFail();

        return [$version, $field];
    }

    private function ensureSupportsOptions(FormField $field): void
    {
        if (! $field->fieldType->has_options) {
            throw ValidationException::withMessages(['options' => 'Este tipo de campo no admite opciones.']);
        }
    }

    private function ensureUniqueValue(FormField $field, string $value, ?int $exceptId = null): void
    {
        $exists = $field->options()
            ->where('option_value', $value)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['option_value' => "El valor de opción '{$value}' ya existe en este campo."]);
        }
    }

    private function hasSingleDefault(FormField $field): bool
    {
        return in_array($field->fieldType->code, ['select', 'radio'], true);
    }
}
