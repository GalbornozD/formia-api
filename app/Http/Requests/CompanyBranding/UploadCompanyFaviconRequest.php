<?php

namespace App\Http\Requests\CompanyBranding;

use Illuminate\Foundation\Http\FormRequest;

class UploadCompanyFaviconRequest extends FormRequest
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
                'max:1024',
                'dimensions:max_width=1024,max_height=1024',
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
            'file.max' => 'El tamaño máximo permitido es 1 MB.',
            'file.dimensions' => 'La imagen supera las dimensiones máximas permitidas (1024×1024 px).',
        ];
    }
}
