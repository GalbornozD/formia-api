<?php

namespace App\Enums;

enum AudienceSourceType: string
{
    case AllUsers = 'all_users';
    case DistributionList = 'distribution_list';
    case SpecificUser = 'specific_user';
    case SpecificGuest = 'specific_guest';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
