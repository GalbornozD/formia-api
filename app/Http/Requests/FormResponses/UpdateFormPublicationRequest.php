<?php

namespace App\Http\Requests\FormResponses;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFormPublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * respondent_type es inmutable despues de creada la publicacion (no se
     * acepta aqui): cambiarlo invalidaria audiencias/asignaciones/invitaciones
     * ya materializadas para la modalidad anterior.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'form_type_version_id' => ['sometimes', 'required', 'integer', 'exists:form_type_versions,id'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'allow_draft' => ['sometimes', 'boolean'],
            'allow_edit_after_submit' => ['sometimes', 'boolean'],
            'show_progress' => ['sometimes', 'boolean'],
            'show_question_numbers' => ['sometimes', 'boolean'],
            'max_responses_per_respondent' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'thank_you_title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'thank_you_description' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
