<?php

namespace App\Support;

use App\Models\User;

class DashboardTab
{
    /** @var list<array{key: string, label: string, department: string, keywords: list<string>}> */
    public const TABS = [
        [
            'key' => 'common',
            'label' => '社員共通',
            'department' => '社員共通',
            'keywords' => [],
        ],
        [
            'key' => 'dispatch',
            'label' => '派遣事業',
            'department' => '派遣事業',
            'keywords' => ['派遣事業', '派遣'],
        ],
        [
            'key' => 'specified-skills',
            'label' => '特定技能',
            'department' => '特定技能',
            'keywords' => ['特定技能'],
        ],
        [
            'key' => 'real-estate',
            'label' => '不動産',
            'department' => '不動産',
            'keywords' => ['不動産'],
        ],
        [
            'key' => 'food',
            'label' => '食品',
            'department' => '食品',
            'keywords' => ['食品'],
        ],
        [
            'key' => 'telecom',
            'label' => '通信',
            'department' => '通信',
            'keywords' => ['通信'],
        ],
        [
            'key' => 'beauty',
            'label' => '美容',
            'department' => '美容',
            'keywords' => ['美容'],
        ],
        [
            'key' => 'company-car',
            'label' => '社用車',
            'department' => '社用車',
            'keywords' => [],
        ],
    ];

    /**
     * @return list<array{key: string, label: string, department: string, keywords: list<string>}>
     */
    public static function all(): array
    {
        return self::TABS;
    }

    /**
     * @return list<string>
     */
    public static function departmentOptions(): array
    {
        return array_map(
            static fn (array $tab) => $tab['department'],
            self::TABS,
        );
    }

    public static function findByKey(string $key): ?array
    {
        foreach (self::TABS as $tab) {
            if ($tab['key'] === $key) {
                return $tab;
            }
        }

        return null;
    }

    public static function findByDepartment(string $department): ?array
    {
        foreach (self::TABS as $tab) {
            if ($tab['department'] === $department) {
                return $tab;
            }
        }

        return null;
    }

    public static function defaultTabKeyFor(User $user): string
    {
        foreach (self::TABS as $tab) {
            if (! in_array($tab['key'], ['common', 'company-car'], true) && self::userMatchesTab($user, $tab)) {
                return $tab['key'];
            }
        }

        return 'common';
    }

    public static function canViewTab(User $user, string $tabKey): bool
    {
        $tab = self::findByKey($tabKey);

        if ($tab === null) {
            return false;
        }

        if (in_array($tab['key'], ['common', 'company-car'], true)) {
            return true;
        }

        return self::userMatchesTab($user, $tab);
    }

    /**
     * @param  array{key: string, label: string, department: string, keywords: list<string>}  $tab
     */
    public static function userMatchesTab(User $user, array $tab): bool
    {
        if ($tab['keywords'] === []) {
            return true;
        }

        $department = $user->currentAffiliation()?->department;

        if (! $department) {
            return false;
        }

        foreach ($tab['keywords'] as $keyword) {
            if (str_contains($department, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{key: string, label: string, department: string, keywords: list<string>, viewable: bool}>
     */
    public static function tabsFor(User $user): array
    {
        return array_map(
            static fn (array $tab) => [
                ...$tab,
                'viewable' => self::canViewTab($user, $tab['key']),
            ],
            self::TABS,
        );
    }
}
