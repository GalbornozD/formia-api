<?php

namespace App\Services\FormBuilder\Validation;

final class ChileanRut
{
    public static function normalize(string $value): string
    {
        $compact = strtoupper((string) preg_replace('/[^0-9kK]/', '', $value));

        if (strlen($compact) < 2) {
            return $compact;
        }

        return substr($compact, 0, -1).'-'.substr($compact, -1);
    }

    public static function isValid(string $value): bool
    {
        $normalized = self::normalize($value);

        if (! preg_match('/^(\d{7,8})-([0-9K])$/', $normalized, $matches)) {
            return false;
        }

        $body = $matches[1];
        $verifier = $matches[2];
        $sum = 0;
        $multiplier = 2;

        for ($index = strlen($body) - 1; $index >= 0; $index--) {
            $sum += ((int) $body[$index]) * $multiplier;
            $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
        }

        $remainder = 11 - ($sum % 11);
        $expected = match ($remainder) {
            11 => '0',
            10 => 'K',
            default => (string) $remainder,
        };

        return $verifier === $expected;
    }
}
