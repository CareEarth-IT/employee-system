<?php

namespace App\Support;

use Carbon\CarbonInterface;

class DateInput
{
    public static function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $matches) === 1) {
            return self::validIso((int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $value, $matches) === 1) {
            return self::validIso((int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        return null;
    }

    public static function toIso(mixed $value): ?string
    {
        return self::normalize($value);
    }

    public static function toDisplay(mixed $value): ?string
    {
        $iso = self::normalize($value);

        if ($iso === null) {
            return null;
        }

        [$year, $month, $day] = explode('-', $iso);

        return sprintf('%s/%s/%s', $year, (string) (int) $month, (string) (int) $day);
    }

    private static function validIso(int $year, int $month, int $day): ?string
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
