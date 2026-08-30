<?php

namespace App\Http\Requests\CompanyBranding;

use App\Enums\ThemeMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyBrandingRequest extends FormRequest
{
    private const REGEX_HEX_COLOR = '/^#[0-9A-Fa-f]{6}$/';

    /**
     * La autorización real (rol + empresa activa) vive en
     * CompanyBrandingPolicy, evaluada en el controller -- acá solo se valida
     * la forma de los datos.
     */
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
            'primaryColor' => ['sometimes', 'string', 'regex:'.self::REGEX_HEX_COLOR],
            'secondaryColor' => ['sometimes', 'string', 'regex:'.self::REGEX_HEX_COLOR],
            'accentColor' => ['sometimes', 'nullable', 'string', 'regex:'.self::REGEX_HEX_COLOR],
            'themeMode' => ['sometimes', Rule::in(array_column(ThemeMode::cases(), 'value'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primaryColor.regex' => 'El color principal debe ser un código HEX válido (ej: #2563EB).',
            'secondaryColor.regex' => 'El color secundario debe ser un código HEX válido (ej: #0F172A).',
            'accentColor.regex' => 'El color de acento debe ser un código HEX válido (ej: #14B8A6).',
            'themeMode.in' => 'El tema debe ser light, dark o system.',
        ];
    }

    /**
     * Solo incluye las claves presentes en la petición -- update parcial
     * explícito, igual que UpdateTipoFormularioRequest::datosParaGuardar().
     *
     * @return array<string, mixed>
     */
    public function toData(): array
    {
        $mapaColumnas = [
            'primaryColor' => 'primary_color',
            'secondaryColor' => 'secondary_color',
            'accentColor' => 'accent_color',
            'themeMode' => 'theme_mode',
        ];

        $datos = [];

        foreach ($mapaColumnas as $campo => $columna) {
            if ($this->has($campo)) {
                $datos[$columna] = $this->validated($campo);
            }
        }

        return $datos;
    }
}
