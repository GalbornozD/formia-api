<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public $table = 'roles';

    public $timestamps = true;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'int';

    public const MASTER = 1;

    public const ADMINISTRADOR = 2;

    public const SUPERVISOR = 3;

    public const VIEWER = 4;

    /**
     * Evita un round-trip a la BD para resolver el nombre de un catálogo
     * fijo de roles (se usa al serializar el usuario en cada respuesta).
     */
    public static function nombreDe(int $id): ?string
    {
        return match ($id) {
            self::MASTER => 'master',
            self::ADMINISTRADOR => 'administrador',
            self::SUPERVISOR => 'supervisor',
            self::VIEWER => 'viewer',
            default => null,
        };
    }

    public static function idDesdeNombre(string $nombre): ?int
    {
        return match ($nombre) {
            'master' => self::MASTER,
            'administrador' => self::ADMINISTRADOR,
            'supervisor' => self::SUPERVISOR,
            'viewer' => self::VIEWER,
            default => null,
        };
    }

    /**
     * Roles que $usuario puede otorgar al crear/editar un usuario: nunca uno
     * con más poder que el propio (role_id menor = más poder — master=1 es
     * el techo). Generaliza "un administrador no puede otorgar master" a
     * cualquier par de roles, para cuando supervisor/viewer entren en uso.
     * Única fuente de verdad para el combo del frontend y la validación.
     *
     * @return Builder<Role>
     */
    public static function asignablesPor(User $usuario): Builder
    {
        return static::query()
            ->where('status', true)
            ->where('id', '>=', $usuario->role_id)
            ->orderBy('id');
    }
}
