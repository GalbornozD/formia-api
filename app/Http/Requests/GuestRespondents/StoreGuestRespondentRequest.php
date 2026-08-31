<?php

namespace App\Http\Requests\GuestRespondents;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGuestRespondentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * La regla "al menos un dato de identificacion" se valida en
     * GuestRespondentService::resolveOrCreate (necesita normalizar antes
     * de decidir si el conjunto quedo vacio).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp_phone' => ['nullable', 'string', 'max:50'],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
