<?php

namespace App\Http\Requests\Usuario;

use App\Models\Role;
use App\Models\User;
use App\Support\Permisos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    /**
     * La autorización real (rol + empresa activa) vive en CompanyUserPolicy,
     * evaluada en el controller — acá solo se valida forma de los datos.
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
            'nombre' => ['sometimes', 'string', 'max:150'],
            'apellido' => ['sometimes', 'string', 'max:150'],
            'role_id' => ['sometimes', 'integer', Rule::in($this->rolesAsignablesIds())],
            'permisos' => ['sometimes', 'array'],
            'permisos.*' => [Rule::in(array_keys(Permisos::map()))],
            'estado_membresia' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Mismo catálogo que expone GET /roles (Role::asignablesPor).
     *
     * @return list<int>
     */
    private function rolesAsignablesIds(): array
    {
        /** @var User $actor */
        $actor = $this->user();

        return Role::asignablesPor($actor)->pluck('id')->all();
    }

    /**
     * @return array{first_name?: string, last_name?: string, role_id?: int, permission?: int, status?: bool}
     */
    public function datosParaServicio(): array
    {
        $datos = [];

        if ($this->has('nombre')) {
            $datos['first_name'] = $this->validated('nombre');
        }

        if ($this->has('apellido')) {
            $datos['last_name'] = $this->validated('apellido');
        }

        if ($this->has('role_id')) {
            $datos['role_id'] = (int) $this->validated('role_id');
        }

        if ($this->has('permisos')) {
            $datos['permission'] = array_reduce(
                $this->validated('permisos'),
                fn (int $bitmask, string $clave) => $bitmask | Permisos::bit($clave),
                0,
            );
        }

        if ($this->has('estado_membresia')) {
            $datos['status'] = (bool) $this->validated('estado_membresia');
        }

        return $datos;
    }
}
