<?php

namespace App\Http\Requests\CompanyBranding;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Usado para logo, logo compacto y logo modo oscuro -- mismas reglas de
 * formato/tamaño para los tres.
 */
class UploadCompanyLogoRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:2048',
                'dimensions:max_width=4000,max_height=4000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Selecciona una imagen para subir.',
            'file.image' => 'El archivo debe ser una imagen.',
            'file.mimes' => 'Formatos permitidos: PNG, JPG o WebP.',
            'file.max' => 'El tamaño máximo permitido es 2 MB.',
            'file.dimensions' => 'La imagen supera las dimensiones máximas permitidas (4000×4000 px).',
        ];
    }
}
