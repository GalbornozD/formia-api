<?php

namespace App\Http\Requests\TipoFormulario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTipoFormularioRequest extends FormRequest
{
    /**
     * La autorizacion real (rol + empresa activa) vive en FormTypePolicy,
     * evaluada en el controller -- aca solo se valida forma de los datos.
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
            'nombre' => [
                'sometimes',
                'string',
                'max:150',
                Rule::unique('form_types', 'name')
                    ->where('company_id', $this->empresaId())
                    ->ignore($this->route('tipoFormulario')),
            ],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'estado' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{name?: string, description?: string|null, status?: bool}
     */
    public function datosParaGuardar(): array
    {
        $datos = [];

        if ($this->has('nombre')) {
            $datos['name'] = $this->validated('nombre');
        }

        if ($this->has('descripcion')) {
            $datos['description'] = $this->validated('descripcion');
        }

        if ($this->has('estado')) {
            $datos['status'] = (bool) $this->validated('estado');
        }

        return $datos;
    }

    private function empresaId(): ?int
    {
        $empresa = $this->route('empresa');

        return is_object($empresa) ? $empresa->id : $empresa;
    }
}
