<?php

namespace App\Services\FormResponses;

use App\Enums\FormAvailabilityStatus;
use App\Models\CompanyBranding;
use App\Models\CompanySettings;
use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormPublication;
use App\Models\FormTypeVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class ResponseFormDefinitionService
{
    public function __construct(private readonly FieldVisibilityResolver $visibilityResolver) {}

    /**
     * @return array<string, mixed>
     */
    public function publicForm(FormPublication $publication, FormAvailabilityStatus $status = FormAvailabilityStatus::Pending): array
    {
        $publication->loadMissing(['formType', 'version', 'empresa.branding', 'empresa.settings']);

        return [
            'publication' => [
                'uuid' => $publication->uuid,
                'name' => $publication->name,
                'slug' => $publication->slug,
                'respondent_type' => $publication->respondent_type->value,
                'starts_at' => $publication->starts_at?->toIso8601String(),
                'ends_at' => $publication->ends_at?->toIso8601String(),
                'allow_draft' => $publication->allow_draft,
                'allow_edit_after_submit' => $publication->allow_edit_after_submit,
                'show_progress' => $publication->show_progress,
                'show_question_numbers' => $publication->show_question_numbers,
                'max_responses_per_respondent' => $publication->max_responses_per_respondent,
                'thank_you' => [
                    'title' => $publication->thank_you_title,
                    'description' => $publication->thank_you_description,
                ],
                'availability_status' => $status->value,
            ],
            'form_type' => [
                'name' => $publication->formType->name,
                'description' => $publication->formType->description,
                'version' => $publication->version->version,
            ],
            'company' => [
                'uuid' => $publication->empresa->uuid,
                'name' => $publication->empresa->legal_name,
            ],
            'branding' => $this->branding($publication->empresa->branding),
            'settings' => $this->settings($publication->empresa->settings),
            'fields' => $this->fields($publication->version),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fields(FormTypeVersion $version): array
    {
        $fields = FormField::query()
            ->where('form_type_version_id', $version->id)
            ->with([
                'fieldType',
                'options' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (FormField $field): bool => $this->visibilityResolver->isVisible($field));

        return $this->tree($fields);
    }

    /** @return array<string, mixed> */
    private function branding(?CompanyBranding $branding): array
    {
        return [
            'logo' => $this->assetUrl($branding?->logo_path),
            'logoDark' => $this->assetUrl($branding?->logo_dark_path),
            'logoCompact' => $this->assetUrl($branding?->logo_compact_path),
            'favicon' => $this->assetUrl($branding?->favicon_path),
            'primaryColor' => $branding?->primary_color ?? '#2563EB',
            'secondaryColor' => $branding?->secondary_color ?? '#0F172A',
            'accentColor' => $branding?->accent_color,
            'themeMode' => $branding?->theme_mode?->value ?? 'light',
            'version' => $branding?->version ?? 1,
        ];
    }

    /** @return array<string, mixed> */
    private function settings(?CompanySettings $settings): array
    {
        return [
            'timezone' => $settings?->timezone ?? 'America/Santiago',
            'locale' => $settings?->locale ?? 'es-CL',
            'dateFormat' => $settings?->date_format ?? 'DD/MM/YYYY',
            'timeFormat' => $settings?->time_format ?? 'HH:mm',
        ];
    }

    private function assetUrl(?string $path): ?string
    {
        return $path !== null ? Storage::disk('public')->url($path) : null;
    }

    /**
     * @param  Collection<int, FormField>  $fields
     * @return list<array<string, mixed>>
     */
    private function tree(Collection $fields): array
    {
        $byParent = $fields->groupBy(fn (FormField $field): int => $field->parent_field_id ?? 0);
        $visited = [];

        $build = function (?int $parentId) use (&$build, &$visited, $byParent): array {
            return $byParent->get($parentId ?? 0, collect())
                ->map(function (FormField $field) use (&$build, &$visited): array {
                    if (isset($visited[$field->id])) {
                        return [];
                    }

                    $visited[$field->id] = true;

                    return [
                        'field_key' => $field->field_key,
                        'label' => $field->label,
                        'description' => $field->description,
                        'placeholder' => $field->placeholder,
                        'default_value' => $field->default_value,
                        'is_required' => $field->is_required,
                        'is_readonly' => $field->is_readonly,
                        'width' => $field->width,
                        'validation_rules' => $field->validation_rules ?? [],
                        'settings' => $field->settings ?? [],
                        'field_type' => $this->fieldType($field->fieldType),
                        'options' => $field->options
                            ->filter(fn (FormFieldOption $option): bool => $option->is_active)
                            ->map(fn (FormFieldOption $option): array => $this->option($option))
                            ->values()
                            ->all(),
                        'children' => $build($field->id),
                    ];
                })
                ->filter()
                ->values()
                ->all();
        };

        return $build(null);
    }

    /** @return array<string, mixed> */
    private function fieldType(FieldType $fieldType): array
    {
        return [
            'code' => $fieldType->code,
            'name' => $fieldType->name,
            'has_options' => $fieldType->has_options,
            'is_container' => $fieldType->is_container,
        ];
    }

    /** @return array<string, mixed> */
    private function option(FormFieldOption $option): array
    {
        return [
            'option_value' => $option->option_value,
            'option_label' => $option->option_label,
            'settings' => $option->settings ?? [],
        ];
    }
}
