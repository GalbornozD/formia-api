<?php

namespace App\Http\Requests\Usuario;

use App\Models\Role;
use App\Models\User;
use App\Support\Permisos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUsuarioRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'nombre' => ['required', 'string', 'max:150'],
            'apellido' => ['required', 'string', 'max:150'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role_id' => ['required', 'integer', Rule::in($this->rolesAsignablesIds())],
            'permisos' => ['sometimes', 'array'],
            'permisos.*' => [Rule::in(array_keys(Permisos::map()))],
        ];
    }

    /**
     * Mismo catálogo que expone GET /roles (Role::asignablesPor): evita que
     * un administrador otorgue un rol con más poder que el propio y mantiene
     * una única fuente de verdad entre el combo del frontend y esta validación.
     *
     * @return list<int>
     */
    private function rolesAsignablesIds(): array
    {
        /** @var User $actor */
        $actor = $this->user();

        return Role::asignablesPor($actor)->pluck('id')->all();
    }

    public function roleId(): int
    {
        return (int) $this->validated('role_id');
    }

    public function permissionBitmask(): int
    {
        $claves = $this->validated('permisos') ?? array_keys(Permisos::map());

        return array_reduce($claves, fn (int $bitmask, string $clave) => $bitmask | Permisos::bit($clave), 0);
    }
}
