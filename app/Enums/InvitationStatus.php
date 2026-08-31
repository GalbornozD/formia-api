<?php

namespace App\Enums;

enum InvitationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Opened = 'opened';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
