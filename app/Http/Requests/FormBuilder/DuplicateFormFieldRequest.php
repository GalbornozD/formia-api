<?php

namespace App\Http\Requests\FormBuilder;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DuplicateFormFieldRequest extends FormRequest
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
            'parent_field_id' => ['sometimes', 'nullable', 'integer', 'exists:form_fields,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
