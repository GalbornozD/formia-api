<?php

namespace App\Services;

use App\Models\FieldType;
use App\Models\FormField;
use App\Models\FormFieldOption;
use App\Models\FormTypeVersion;
use Illuminate\Database\Eloquent\Collection;

final class FormDefinitionService
{
    /** @return array<string, mixed> */
    public function builder(FormTypeVersion $version): array
    {
        $version->load('formType');

        return [
            ...$this->version($version),
            'form_type' => [
                'id' => $version->formType->id,
                'company_id' => $version->formType->company_id,
                'name' => $version->formType->name,
                'description' => $version->formType->description,
                'status' => $version->formType->status,
            ],
            'field_types' => FieldType::query()
                ->orderBy('id')
                ->get()
                ->map(fn (FieldType $fieldType): array => $this->fieldType($fieldType))
                ->values()
                ->all(),
            'fields' => $this->fields($version),
        ];
    }

    /** @return list<array<string, mixed>> */
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
            ->get();

        return $this->tree($fields);
    }

    /** @return array<string, mixed> */
    public function field(FormField $field): array
    {
        $tree = $this->fields($field->formTypeVersion);
        $found = $this->findInTree($tree, $field->id);

        abort_if($found === null, 404);

        return $found;
    }

    /** @return array<string, mixed> */
    public function version(FormTypeVersion $version): array
    {
        return [
            'id' => $version->id,
            'form_type_id' => $version->form_type_id,
            'version' => $version->version,
            'is_published' => $version->is_published,
            'is_active' => $version->is_active,
            'published_at' => $version->published_at,
            'created_at' => $version->created_at,
            'updated_at' => $version->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    public function option(FormFieldOption $option): array
    {
        return [
            'id' => $option->id,
            'form_field_id' => $option->form_field_id,
            'option_value' => $option->option_value,
            'option_label' => $option->option_label,
            'sort_order' => $option->sort_order,
            'is_default' => $option->is_default,
            'is_active' => $option->is_active,
            'settings' => $option->settings ?? [],
        ];
    }

    /** @return array<string, mixed> */
    public function fieldType(FieldType $fieldType): array
    {
        return [
            'id' => $fieldType->id,
            'code' => $fieldType->code,
            'name' => $fieldType->name,
            'has_options' => $fieldType->has_options,
            'is_container' => $fieldType->is_container,
            'is_active' => $fieldType->is_active,
        ];
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
                        'id' => $field->id,
                        'form_type_version_id' => $field->form_type_version_id,
                        'field_type_id' => $field->field_type_id,
                        'parent_field_id' => $field->parent_field_id,
                        'field_key' => $field->field_key,
                        'label' => $field->label,
                        'description' => $field->description,
                        'placeholder' => $field->placeholder,
                        'default_value' => $field->default_value,
                        'is_required' => $field->is_required,
                        'is_readonly' => $field->is_readonly,
                        'is_hidden' => $field->is_hidden,
                        'is_active' => $field->is_active,
                        'sort_order' => $field->sort_order,
                        'width' => $field->width,
                        'validation_rules' => $field->validation_rules ?? [],
                        'settings' => $field->settings ?? [],
                        'field_type' => $this->fieldType($field->fieldType),
                        'options' => $field->options
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

    /**
     * @param  list<array<string, mixed>>  $tree
     * @return array<string, mixed>|null
     */
    private function findInTree(array $tree, int $id): ?array
    {
        foreach ($tree as $field) {
            if ($field['id'] === $id) {
                return $field;
            }

            $found = $this->findInTree($field['children'], $id);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
