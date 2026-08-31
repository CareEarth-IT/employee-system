<?php

namespace App\Support;

class RegistryDepartmentOptions
{
    /** @var list<string> 社員登録フォームの所属部署 */
    public const OPTIONS = [
        '経営企画室',
        '管理本部',
        '派遣事業部',
        '不動産事業部',
        '通信事業部',
        '特定技能事業部',
        '食品事業部',
        '美容事業部',
        '経理部',
        '情報システム部',
        '人事部',
        '大阪管理部',
        '大阪営業部',
        '大阪グローバル事業部',
        '東京営業部',
        '東京グローバル事業部',
        '東京管理部',
        '名古屋営業部',
        '名古屋グローバル事業部',
        '福岡営業部',
        '福岡グローバル事業部',
        'ベトナム支店',
        'M&A戦略推進部',
    ];

    /**
     * 社員登録の所属部署ごとに付与する Top Page タブ（社員共通・社用車を除く）。
     *
     * @var array<string, list<string>>
     */
    public const DASHBOARD_TABS = [
        '派遣事業部' => ['dispatch'],
        '不動産事業部' => ['real-estate'],
        '通信事業部' => ['telecom'],
        '特定技能事業部' => ['specified-skills'],
        '食品事業部' => ['food'],
        '美容事業部' => ['beauty'],
        '経理部' => ['specified-skills', 'real-estate'],
    ];

    /**
     * @var array<string, array{department: string, section: ?string}>
     */
    private const AFFILIATION_MAP = [
        '経営企画室' => ['department' => '経営企画室', 'section' => null],
        '管理本部' => ['department' => '管理本部', 'section' => null],
        '派遣事業部' => ['department' => '派遣事業部', 'section' => null],
        '不動産事業部' => ['department' => '不動産事業部', 'section' => null],
        '通信事業部' => ['department' => '通信事業部', 'section' => null],
        '特定技能事業部' => ['department' => '特定技能事業部', 'section' => null],
        '食品事業部' => ['department' => '食品事業部', 'section' => null],
        '美容事業部' => ['department' => '美容事業部', 'section' => null],
        '経理部' => ['department' => '経理部', 'section' => null],
        '情報システム部' => ['department' => '情報システム部', 'section' => null],
        '人事部' => ['department' => '人事部', 'section' => null],
        '大阪管理部' => ['department' => '大阪管理部', 'section' => null],
        '大阪営業部' => ['department' => '大阪営業部', 'section' => null],
        '大阪グローバル事業部' => ['department' => '大阪グローバル事業部', 'section' => null],
        '東京営業部' => ['department' => '東京営業部', 'section' => null],
        '東京グローバル事業部' => ['department' => '東京グローバル事業部', 'section' => null],
        '東京管理部' => ['department' => '東京管理部', 'section' => null],
        '名古屋営業部' => ['department' => '名古屋営業部', 'section' => null],
        '名古屋グローバル事業部' => ['department' => '名古屋グローバル事業部', 'section' => null],
        '福岡営業部' => ['department' => '福岡営業部', 'section' => null],
        '福岡グローバル事業部' => ['department' => '福岡グローバル事業部', 'section' => null],
        'ベトナム支店' => ['department' => 'ベトナム支店', 'section' => null],
        'M&A戦略推進部' => ['department' => 'M&A戦略推進部', 'section' => null],
    ];

    /**
     * @return list<string>
     */
    public static function options(): array
    {
        return self::OPTIONS;
    }

    public static function isRegistryDepartment(?string $department): bool
    {
        return $department !== null
            && $department !== ''
            && in_array($department, self::OPTIONS, true);
    }

    /**
     * @return list<string>
     */
    public static function dashboardTabsFor(?string $registryDepartment): array
    {
        if (! self::isRegistryDepartment($registryDepartment)) {
            return [];
        }

        return self::DASHBOARD_TABS[$registryDepartment] ?? [];
    }

    /**
     * @return array{department: string, section: ?string}
     */
    public static function resolveAffiliation(string $registryDepartment): array
    {
        return self::AFFILIATION_MAP[$registryDepartment]
            ?? ['department' => $registryDepartment, 'section' => null];
    }

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
