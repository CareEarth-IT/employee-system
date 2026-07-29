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
}
