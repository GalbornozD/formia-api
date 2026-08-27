<?php

namespace App\Support;

/**
 * Catálogo de permisos como bits de company_user.permission (0-15).
 * Autorización fina por módulo (ej. solicitudes) a definir más adelante;
 * hoy la gestión de usuarios/roles se autoriza por rol global
 * (User::esMaster()/esAdministrador()), no por este bitmask.
 */
final class Permisos
{
    public const LEER = 1 << 0;

    public const CREAR = 1 << 1;

    public const ACTUALIZAR = 1 << 2;

    public const ELIMINAR = 1 << 3;

    /**
     * @return array<string, int>
     */
    public static function map(): array
    {
        return [
            'leer' => self::LEER,
            'crear' => self::CREAR,
            'actualizar' => self::ACTUALIZAR,
            'eliminar' => self::ELIMINAR,
        ];
    }

    public static function bit(string $clave): int
    {
        return self::map()[$clave]
            ?? throw new \InvalidArgumentException("Permiso desconocido: {$clave}");
    }

    /**
     * @return list<string> claves activas dentro del bitmask, para exponer al frontend.
     */
    public static function clavesDesdeBitmask(int $bitmask): array
    {
        return array_values(array_keys(array_filter(
            self::map(),
            fn (int $bit) => ($bitmask & $bit) === $bit,
        )));
    }
}
