<?php

namespace App\Http\Requests\FormResponses;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * La pertenencia real a la empresa de cada id se valida en
 * PublicationAudienceService (igual que FormPublicationService::assign) —
 * aquí solo se valida forma/tipo, nunca confiando en el backend
 * frontend para decidir qué respondent_type tiene la publicación.
 */
class StorePublicationAudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'all_users' => ['sometimes', 'boolean'],
            'distribution_list_ids' => ['sometimes', 'array', 'max:100'],
            'distribution_list_ids.*' => ['integer', 'distinct'],
            'user_ids' => ['sometimes', 'array', 'max:5000'],
            'user_ids.*' => ['integer', 'distinct'],
            'guest_respondent_ids' => ['sometimes', 'array', 'max:5000'],
            'guest_respondent_ids.*' => ['string', 'distinct'],
        ];
    }
}
