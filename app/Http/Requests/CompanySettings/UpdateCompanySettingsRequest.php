<?php

namespace App\Http\Requests\CompanySettings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanySettingsRequest extends FormRequest
{
    /**
     * Formatos soportados hoy por el selector del frontend. Lista acotada a
     * propósito -- evita guardar un patrón que ninguna librería de fechas
     * del frontend sepa interpretar.
     */
    private const DATE_FORMATS = ['DD/MM/YYYY', 'MM/DD/YYYY', 'YYYY-MM-DD'];

    private const TIME_FORMATS = ['HH:mm', 'hh:mm A'];

    /**
     * La autorización real (rol + empresa activa) vive en
     * CompanySettingsPolicy, evaluada en el controller -- acá solo se valida
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
            'timezone' => ['sometimes', 'string', 'max:100', Rule::in(timezone_identifiers_list())],
            'locale' => ['sometimes', 'string', 'regex:/^[a-z]{2}-[A-Z]{2}$/'],
            'dateFormat' => ['sometimes', 'string', Rule::in(self::DATE_FORMATS)],
            'timeFormat' => ['sometimes', 'string', Rule::in(self::TIME_FORMATS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'timezone.in' => 'La zona horaria indicada no es válida.',
            'locale.regex' => 'El idioma debe tener el formato "es-CL".',
            'dateFormat.in' => 'El formato de fecha indicado no es válido.',
            'timeFormat.in' => 'El formato de hora indicado no es válido.',
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
            'timezone' => 'timezone',
            'locale' => 'locale',
            'dateFormat' => 'date_format',
            'timeFormat' => 'time_format',
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
