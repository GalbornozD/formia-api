<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Compartida entre POST /seleccionar-empresa (post-login) y
 * POST /cambiar-empresa (sesión ya autenticada): misma regla en ambos
 * casos, exige membresía `activo` del usuario autenticado en esa empresa.
 */
class SeleccionarEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'empresa_id' => [
                'required',
                'integer',
                Rule::exists('company_user', 'company_id')
                    ->where('user_id', $this->user()?->id)
                    ->where('status', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'empresa_id.exists' => 'No tienes una membresía activa en esa empresa.',
        ];
    }
}
