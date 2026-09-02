<?php

namespace App\Support;

class RegistrySectionByAssignment
{
    /**
     * 社員登録フォーム: 部署（＋管轄）ごとに選択可能な課。
     *
     * @var array<string, array<string, list<string>>>
     */
    public const ASSIGNMENTS = [
        '経理部' => [
            '*' => ['経理課', '総務課'],
        ],
        '人事部' => [
            '*' => ['人事課', '人材開発・人材育成課', '広報・マーケティング課'],
        ],
        'Food GA部' => [
            '*' => ['総務課'],
        ],
        '管理部' => [
            '大阪' => ['業務課', '労務法務課', '情報管理課'],
            '東京' => ['業務課'],
        ],
        '営業部' => [
            '大阪' => ['人材育成課', 'SS課', '営業1課', '営業2課', '営業3課', '営業4課'],
            '東京' => ['営業1課', '営業2課', 'SS課'],
            '名古屋' => ['営業課_名古屋', 'SS課_名古屋'],
            '福岡' => ['営業課_福岡', 'SS課_福岡'],
        ],
        'GR部（グローバル部）' => [
            '大阪' => RegistryGrAssignment::DIVISIONS,
            '福岡' => RegistryGrAssignment::DIVISIONS,
            '東京' => RegistryGrAssignment::DIVISIONS,
            '名古屋' => RegistryGrAssignment::DIVISIONS,
            'ベトナム' => ['GR-S部', '管理部業務課'],
        ],
    ];

    /** @var list<string> 部署に紐づかず課のみで所属する選択肢 */
    public const STANDALONE_SECTIONS = [
        '庶務課',
    ];

    /**
     * @return list<string>
     */
    public static function standaloneSections(): array
    {
        return self::STANDALONE_SECTIONS;
    }

    /**
     * @return list<string>
     */
    public static function optionsFor(?string $department, ?string $location): array
    {
        $department = trim((string) $department);
        $location = trim((string) $location);

        if ($department === '' || ! isset(self::ASSIGNMENTS[$department])) {
            return self::standaloneSections();
        }

        $rules = self::ASSIGNMENTS[$department];

        if (isset($rules['*'])) {
            return $rules['*'];
        }

        if ($location === '' || ! isset($rules[$location])) {
            return [];
        }

        return $rules[$location];
    }

    public static function hasRules(?string $department): bool
    {
        $department = trim((string) $department);

        return $department !== '' && isset(self::ASSIGNMENTS[$department]);
    }

    public static function shouldShowField(
        ?string $department,
        ?string $location,
        ?string $current = null,
    ): bool {
        return true;
    }

    public static function requiresLocation(?string $department): bool
    {
        if (! self::hasRules($department)) {
            return false;
        }

        return ! isset(self::ASSIGNMENTS[trim((string) $department)]['*']);
    }

    /**
     * @return list<string>
     */
    public static function forSelect(?string $department, ?string $location, ?string $current = null): array
    {
        $options = self::optionsFor($department, $location);

        if ($current === null || $current === '' || in_array($current, $options, true)) {
            return $options;
        }

        if ($department === RegistryGrAssignment::DEPARTMENT) {
            $formSection = RegistryGrAssignment::toFormValues(
                (string) $location,
                $current,
                null,
            )['section'];

            if ($formSection !== null && $formSection !== '' && in_array($formSection, $options, true)) {
                return $options;
            }
        }

        return [...$options, $current];
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    public static function clientMap(): array
    {
        return self::ASSIGNMENTS;
    }
}
