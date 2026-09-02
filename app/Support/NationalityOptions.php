<?php

namespace App\Support;

class NationalityOptions
{
    /** @var array<string, string> 国コード → 表示名 */
    public const CODE_TO_NAME = [
        'BD' => 'バングラデシュ',
        'ID' => 'インドネシア',
        'JP' => '日本',
        'LK' => 'スリランカ',
        'MM' => 'ミャンマー',
        'NP' => 'ネパール',
        'TW' => '台湾',
        'VN' => 'ベトナム',
        'KR' => '韓国',
        'IN' => 'インド',
        'CN' => '中国',
    ];

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_values(self::CODE_TO_NAME);
    }

    public static function mapCodeToName(string $code): ?string
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return null;
        }

        return self::CODE_TO_NAME[$code] ?? null;
    }

    /**
     * CSV の国コード、または登録済み表示名をポータル保存用の表示名に変換する。
     * 未登録のコード・文字列は null（DB にコードを保存しない）。
     */
    public static function toDisplayName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $fromCode = self::mapCodeToName($value);

        if ($fromCode !== null) {
            return $fromCode;
        }

        if (in_array($value, self::names(), true)) {
            return $value;
        }

        return null;
    }

    /** @deprecated use toDisplayName() */
    public static function normalize(?string $value): ?string
    {
        return self::toDisplayName($value);
    }
}
