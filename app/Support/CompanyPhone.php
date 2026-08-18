<?php

namespace App\Support;

class CompanyPhone
{
    public const MAX_LENGTH = 255;

    /** @var list<string> */
    public const SKIP_VALUES = ['―', '-', '—', '–'];

    /**
     * @return list<string>
     */
    public static function parse(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $parts = preg_split('/[,、\/;]+/u', $value) ?: [];
        $phones = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '' || in_array($part, self::SKIP_VALUES, true)) {
                continue;
            }

            $phones[] = $part;
        }

        return array_values(array_unique($phones));
    }

    public static function normalize(?string $value): ?string
    {
        $phones = self::parse($value);

        if ($phones === []) {
            return null;
        }

        return mb_substr(implode(', ', $phones), 0, self::MAX_LENGTH);
    }

    /** 画面表示用（例: 08073604364 → 080-7360-4364） */
    public static function display(?string $value): ?string
    {
        $phones = self::parse($value);

        if ($phones === []) {
            return null;
        }

        return implode(', ', array_map(
            static fn (string $phone) => self::format($phone) ?? $phone,
            $phones,
        ));
    }

    public static function format(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || in_array($trimmed, self::SKIP_VALUES, true)) {
            return null;
        }

        $digits = preg_replace('/\D/u', '', $trimmed) ?? '';

        if ($digits === '') {
            return $trimmed;
        }

        $length = strlen($digits);

        if ($length === 11 && preg_match('/^0(50|70|80|90)/', $digits)) {
            return sprintf('%s-%s-%s', substr($digits, 0, 3), substr($digits, 3, 4), substr($digits, 7, 4));
        }

        if ($length === 10 && preg_match('/^0[36]/', $digits)) {
            return sprintf('%s-%s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6, 4));
        }

        if ($length === 10 && str_starts_with($digits, '0120')) {
            return sprintf('%s-%s-%s', substr($digits, 0, 4), substr($digits, 4, 3), substr($digits, 7, 3));
        }

        if ($length === 10) {
            return sprintf('%s-%s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 4));
        }

        if ($length === 11) {
            return sprintf('%s-%s-%s', substr($digits, 0, 3), substr($digits, 3, 4), substr($digits, 7, 4));
        }

        return $trimmed;
    }
}
