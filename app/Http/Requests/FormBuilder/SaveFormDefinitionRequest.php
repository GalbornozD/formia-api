<?php

namespace App\Http\Requests\FormBuilder;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFormDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('version')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fields' => ['present', 'array', 'max:500'],
            'fields.*' => ['required', 'array'],
            'fields.*.id' => ['sometimes', 'integer', 'min:1', 'distinct'],
            'fields.*.client_id' => ['required', 'string', 'max:100', 'distinct'],
            'fields.*.parent_client_id' => ['present', 'nullable', 'string', 'max:100'],
            'fields.*.field_type_id' => ['required', 'integer', 'exists:field_types,id'],
            'fields.*.field_key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.description' => ['present', 'nullable', 'string', 'max:20000'],
            'fields.*.placeholder' => ['present', 'nullable', 'string', 'max:255'],
            'fields.*.default_value' => ['present', 'nullable'],
            'fields.*.is_required' => ['required', 'boolean'],
            'fields.*.is_readonly' => ['required', 'boolean'],
            'fields.*.is_hidden' => ['required', 'boolean'],
            'fields.*.is_active' => ['required', 'boolean'],
            'fields.*.sort_order' => ['required', 'integer', 'min:0'],
            'fields.*.width' => ['required', 'integer', Rule::in([3, 4, 6, 8, 9, 12])],
            'fields.*.validation_rules' => ['present', 'array'],
            'fields.*.settings' => ['present', 'array'],
            'fields.*.options' => ['present', 'array', 'max:200'],
            'fields.*.options.*' => ['required', 'array'],
            'fields.*.options.*.id' => ['sometimes', 'integer', 'min:1', 'distinct'],
            'fields.*.options.*.option_value' => ['required', 'string', 'max:255'],
            'fields.*.options.*.option_label' => ['required', 'string', 'max:255'],
            'fields.*.options.*.sort_order' => ['required', 'integer', 'min:0'],
            'fields.*.options.*.is_default' => ['required', 'boolean'],
            'fields.*.options.*.is_active' => ['required', 'boolean'],
            'fields.*.options.*.settings' => ['present', 'array'],
        ];
    }
}
