<?php

namespace App\Support;

class RegistryDepartmentOptions
{
    /** @var list<string> 社員登録フォームの所属部署 */
    public const OPTIONS = [
        'M&A戦略推進部',
        '美容事業部',
        '不動産事業部',
        '通信事業部',
        '特定技能事業部',
        '経理部',
        '情報システム部',
        '人事部',
        'Food Sales部',
        'Food Retail部',
        'Food Logistic部',
        'Food GA部',
        '管理部',
        '営業部',
        'GR部（グローバル部）',
    ];

    /**
     * 社員登録の所属部署ごとに付与する Top Page タブ（社員共通・社用車を除く）。
     *
     * @var array<string, list<string>>
     */
    public const DASHBOARD_TABS = [
        '美容事業部' => ['beauty'],
        '不動産事業部' => ['real-estate'],
        '通信事業部' => ['telecom'],
        '特定技能事業部' => ['specified-skills'],
        '経理部' => ['specified-skills', 'real-estate'],
        'Food Sales部' => ['food'],
        'Food Retail部' => ['food'],
        'Food Logistic部' => ['food'],
        'Food GA部' => ['food'],
    ];

    /**
     * @var array<string, array{department: string, section: ?string}>
     */
    private const AFFILIATION_MAP = [
        'M&A戦略推進部' => ['department' => 'M&A戦略推進部', 'section' => null],
        '美容事業部' => ['department' => '美容事業部', 'section' => null],
        '不動産事業部' => ['department' => '不動産事業部', 'section' => null],
        '通信事業部' => ['department' => '通信事業部', 'section' => null],
        '特定技能事業部' => ['department' => '特定技能事業部', 'section' => null],
        '経理部' => ['department' => '経理部', 'section' => null],
        '情報システム部' => ['department' => '情報システム部', 'section' => null],
        '人事部' => ['department' => '人事部', 'section' => null],
        'Food Sales部' => ['department' => 'Food Sales部', 'section' => null],
        'Food Retail部' => ['department' => 'Food Retail部', 'section' => null],
        'Food Logistic部' => ['department' => 'Food Logistic部', 'section' => null],
        'Food GA部' => ['department' => 'Food GA部', 'section' => null],
        '管理部' => ['department' => '管理部', 'section' => null],
        '営業部' => ['department' => '営業部', 'section' => null],
        'GR部（グローバル部）' => ['department' => 'GR部（グローバル部）', 'section' => null],
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
    public static function resolveAffiliation(string $registryDepartment, ?string $section = null): array
    {
        if (trim((string) $section) === '庶務課') {
            return ['department' => '管理本部', 'section' => null];
        }

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
