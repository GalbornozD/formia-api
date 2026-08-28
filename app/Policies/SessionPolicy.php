<?php

namespace App\Policies;

use App\Models\Session;
use App\Models\User;

/**
 * Autogestión de dispositivos: un usuario solo puede cerrar sus propias
 * sesiones, nunca las de otro — ni siquiera master (esto no es
 * administración, es la sesión propia de cada uno).
 */
class SessionPolicy
{
    /** Listado "todas las sesiones" (cualquier usuario) — solo master. */
    public function viewAny(User $usuario): bool
    {
        return $usuario->esMaster();
    }

    public function delete(User $usuario, Session $sesion): bool
    {
        return $sesion->user_id === $usuario->id;
    }
}
