<?php

namespace App\Services;

use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormType;
use App\Models\FormTypeVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FormVersionService
{
    public function __construct(private readonly FormFieldService $fieldService) {}

    public function clone(FormType $formType, ?int $sourceVersionId, User $actor): FormTypeVersion
    {
        return DB::transaction(function () use ($formType, $sourceVersionId, $actor): FormTypeVersion {
            $formType = FormType::query()->whereKey($formType->id)->lockForUpdate()->firstOrFail();
            $source = $sourceVersionId === null
                ? $formType->versions()->orderByDesc('version')->first()
                : $formType->versions()->whereKey($sourceVersionId)->first();

            if ($sourceVersionId !== null && $source === null) {
                throw ValidationException::withMessages([
                    'source_version_id' => 'La versión de origen no pertenece a este formulario.',
                ]);
            }

            $version = new FormTypeVersion;
            $version->forceFill([
                'form_type_id' => $formType->id,
                'version' => ((int) $formType->versions()->max('version')) + 1,
                'is_published' => false,
                'is_active' => true,
                'published_at' => null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            if ($source !== null) {
                $this->cloneDefinition($source, $version, $actor);
            }

            return $version->refresh();
        });
    }

    public function publish(FormType $formType, FormTypeVersion $version, User $actor): FormTypeVersion
    {
        return DB::transaction(function () use ($formType, $version, $actor): FormTypeVersion {
            $formType = FormType::query()->whereKey($formType->id)->lockForUpdate()->firstOrFail();
            $version = FormTypeVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            abort_unless($version->form_type_id === $formType->id, 404);

            if (! $formType->status) {
                abort(409, 'No se puede publicar un formulario inactivo.');
            }

            if ($version->is_published) {
                return $version;
            }

            if (! $version->is_active) {
                abort(409, 'No se puede publicar una versión inactiva.');
            }

            $this->fieldService->validateForPublishing($version);
            $version->forceFill([
                'is_published' => true,
                'published_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            return $version->refresh();
        });
    }

    private function cloneDefinition(FormTypeVersion $source, FormTypeVersion $target, User $actor): void
    {
        $sourceFields = FormField::query()
            ->where('form_type_version_id', $source->id)
            ->with('options')
            ->orderBy('id')
            ->get();
        $newIds = [];

        foreach ($sourceFields as $sourceField) {
            $field = new FormField;
            $field->forceFill([
                'form_type_version_id' => $target->id,
                'field_type_id' => $sourceField->field_type_id,
                'parent_field_id' => null,
                'field_key' => $sourceField->field_key,
                'label' => $sourceField->label,
                'description' => $sourceField->description,
                'placeholder' => $sourceField->placeholder,
                'default_value' => $sourceField->default_value,
                'is_required' => $sourceField->is_required,
                'is_readonly' => $sourceField->is_readonly,
                'is_hidden' => $sourceField->is_hidden,
                'is_active' => $sourceField->is_active,
                'sort_order' => $sourceField->sort_order,
                'width' => $sourceField->width,
                'validation_rules' => $sourceField->validation_rules,
                'settings' => $sourceField->settings,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();
            $newIds[$sourceField->id] = $field->id;

            foreach ($sourceField->options as $sourceOption) {
                $option = new FormFieldOption;
                $option->forceFill([
                    'form_field_id' => $field->id,
                    'option_value' => $sourceOption->option_value,
                    'option_label' => $sourceOption->option_label,
                    'sort_order' => $sourceOption->sort_order,
                    'is_default' => $sourceOption->is_default,
                    'is_active' => $sourceOption->is_active,
                    'settings' => $sourceOption->settings,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ])->save();
            }
        }

        foreach ($sourceFields as $sourceField) {
            if ($sourceField->parent_field_id === null) {
                continue;
            }

            FormField::query()->whereKey($newIds[$sourceField->id])->update([
                'parent_field_id' => $newIds[$sourceField->parent_field_id],
                'updated_by' => $actor->id,
            ]);
        }
    }
}
