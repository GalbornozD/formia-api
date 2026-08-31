<?php

namespace App\Http\Requests\FormBuilder;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormFieldRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('field')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'field_type_id' => ['sometimes', 'integer', 'exists:field_types,id'],
            'parent_field_id' => ['sometimes', 'nullable', 'integer', 'exists:form_fields,id'],
            'field_key' => ['sometimes', 'string', 'max:100', 'regex:/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/'],
            'label' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'placeholder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_value' => ['sometimes', 'nullable'],
            'is_required' => ['sometimes', 'boolean'],
            'is_readonly' => ['sometimes', 'boolean'],
            'is_hidden' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'width' => ['sometimes', 'integer', Rule::in([3, 4, 6, 8, 9, 12])],
            'validation_rules' => ['sometimes', 'array'],
            'settings' => ['sometimes', 'array'],
        ];
    }
}
