<?php

namespace App\Support;

class RegistryPositionOptions
{
    /** @var list<string> 人事詳細・社員名簿の役職 */
    public const OPTIONS = [
        'リーダー',
        '課長代理',
        '課長',
        '次長',
        '部長',
        '支店長',
        '代表',
        '執行役員',
        '東日本統括本部長',
        '西日本統括本部長',
    ];

    /**
     * @return list<string>
     */
    public static function forSelect(?string $current = null): array
    {
        if ($current === null || $current === '' || in_array($current, self::OPTIONS, true)) {
            return self::OPTIONS;
        }

        return [...self::OPTIONS, $current];
    }
}
