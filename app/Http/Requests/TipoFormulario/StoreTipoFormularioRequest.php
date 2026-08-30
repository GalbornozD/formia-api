<?php

namespace App\Http\Requests\TipoFormulario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTipoFormularioRequest extends FormRequest
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
                'required',
                'string',
                'max:150',
                Rule::unique('form_types', 'name')->where('company_id', $this->empresaId()),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * El binding implicito de ruta ya resolvio {empresa} a un modelo Company
     * antes de que este FormRequest se valide -- pero por robustez soportamos
     * tambien el caso en que route('empresa') devuelva el id crudo.
     */
    private function empresaId(): ?int
    {
        $empresa = $this->route('empresa');

        return is_object($empresa) ? $empresa->id : $empresa;
    }
}
