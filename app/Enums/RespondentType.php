<?php

namespace App\Enums;

enum RespondentType: string
{
    case User = 'user';
    case Guest = 'guest';
    case Anonymous = 'anonymous';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
