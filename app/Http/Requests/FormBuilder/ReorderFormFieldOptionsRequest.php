<?php

namespace App\Http\Requests\FormBuilder;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderFormFieldOptionsRequest extends FormRequest
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
            'options' => ['required', 'array'],
            'options.*.id' => ['required', 'integer', 'distinct', 'exists:form_field_options,id'],
            'options.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
