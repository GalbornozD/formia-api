<?php

namespace App\Enums;

enum PublicationAccessMode: string
{
    case Authenticated = 'authenticated';
    case Guest = 'guest';
    case Both = 'both';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function allowsAuthenticated(): bool
    {
        return in_array($this, [self::Authenticated, self::Both], true);
    }

    public function allowsGuest(): bool
    {
        return in_array($this, [self::Guest, self::Both], true);
    }
}
