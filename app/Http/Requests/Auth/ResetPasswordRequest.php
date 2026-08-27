<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string'],
            // Password::defaults(): min 12 (NIST 800-63B) + chequeo contra
            // contraseñas filtradas (HIBP), configurado en AppServiceProvider.
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
