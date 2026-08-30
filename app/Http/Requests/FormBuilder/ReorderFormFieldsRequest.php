<?php

namespace App\Http\Requests\FormBuilder;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderFormFieldsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('version')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array'],
            'fields.*.id' => ['required', 'integer', 'distinct', 'exists:form_fields,id'],
            'fields.*.parent_field_id' => ['present', 'nullable', 'integer', 'exists:form_fields,id'],
            'fields.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
