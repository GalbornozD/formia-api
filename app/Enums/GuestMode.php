<?php

namespace App\Enums;

enum GuestMode: string
{
    case Anonymous = 'anonymous';
    case Identified = 'identified';
    case Both = 'both';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function allowsAnonymous(): bool
    {
        return in_array($this, [self::Anonymous, self::Both], true);
    }

    public function allowsIdentified(): bool
    {
        return in_array($this, [self::Identified, self::Both], true);
    }
}
