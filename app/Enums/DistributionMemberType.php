<?php

namespace App\Enums;

enum DistributionMemberType: string
{
    case User = 'user';
    case Guest = 'guest';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
