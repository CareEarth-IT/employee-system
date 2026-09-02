<?php

namespace App\Support;

class RegistryTeamOptions
{
    /** @var list<string> 社員登録フォームのチーム（マスタ一覧） */
    public const ALL_OPTIONS = [
        'GR-O CS課 エリア担当チーム_東京',
        'GR-O CS課 固定現場チーム_東京',
        'GR-O CS課 エリア担当チーム_名古屋',
        'GR-O CS課 固定現場チーム_名古屋',
        '法人チーム',
        'GR-O CS課 固定現場チーム_大阪',
        'GR-O CS課 エリア担当チーム_大阪',
        'ECチーム',
        '運送チーム',
        '出荷チーム',
        'GR-O CS課 エリア担当チーム_福岡',
        'GR-O CS課 固定現場チーム_福岡',
    ];

    /**
     * @return list<string>
     */
    public static function options(): array
    {
        return self::ALL_OPTIONS;
    }

    /**
     * @return list<string>
     */
    public static function forSelect(?string $current = null): array
    {
        $options = self::options();

        if ($current === null || $current === '' || in_array($current, $options, true)) {
            return $options;
        }

        return [...$options, $current];
    }

    public static function isTeam(string $value): bool
    {
        return in_array($value, self::ALL_OPTIONS, true);
    }
}
