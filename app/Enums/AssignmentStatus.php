<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Pending = 'pending';
    case Started = 'started';
    case Submitted = 'submitted';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
