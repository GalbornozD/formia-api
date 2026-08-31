<?php

namespace App\Http\Requests\FormResponses;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StartFormResponseRequest extends FormRequest
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
            'respondent' => ['sometimes', 'nullable', 'array'],
            'respondent.name' => ['nullable', 'string', 'max:150'],
            'respondent.email' => ['nullable', 'email', 'max:255'],
            'respondent.phone' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'max:10'],
            'answers' => ['sometimes', 'array'],
            'answers.*.field_key' => ['required_with:answers', 'string', 'max:100'],
            'answers.*.value' => ['present', 'nullable'],
        ];
    }
}
