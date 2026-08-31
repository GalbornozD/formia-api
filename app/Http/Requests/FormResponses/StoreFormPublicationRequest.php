<?php

namespace App\Http\Requests\FormResponses;

use App\Enums\RespondentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormPublicationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'form_type_version_id' => ['required', 'integer', 'exists:form_type_versions,id'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'respondent_type' => ['required', Rule::in(RespondentType::values())],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'allow_draft' => ['sometimes', 'boolean'],
            'allow_edit_after_submit' => ['sometimes', 'boolean'],
            'show_progress' => ['sometimes', 'boolean'],
            'show_question_numbers' => ['sometimes', 'boolean'],
            'max_responses_per_respondent' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'thank_you_title' => ['nullable', 'string', 'max:150'],
            'thank_you_description' => ['nullable', 'string', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
