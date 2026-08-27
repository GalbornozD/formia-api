<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Traduce columnas VARBINARY(16) (inet_pton) hacia/desde una IP legible,
 * evitando guardar direcciones IP como texto plano en columnas indexables.
 *
 * @implements CastsAttributes<?string, ?string>
 */
class BinaryIp implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return inet_ntop($value) ?: null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return inet_pton($value) ?: null;
    }
}
