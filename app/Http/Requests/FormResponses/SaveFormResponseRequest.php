<?php

namespace App\Http\Requests\FormResponses;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveFormResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'answers' => ['present', 'array'],
            'answers.*.field_key' => ['required', 'string', 'max:100'],
            'answers.*.value' => ['present', 'nullable'],
        ];
    }
}
