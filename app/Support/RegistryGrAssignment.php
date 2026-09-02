<?php

namespace App\Support;

class RegistryGrAssignment
{
    public const DEPARTMENT = 'GR部（グローバル部）';

    /** @var list<string> */
    public const DIVISIONS = [
        'GR-C部',
        'GR-S部',
        'GR-M部',
        'GR-O部',
    ];

    /**
     * 管轄ごとの GR チーム定義（表示ラベル => 保存値、CS課のみ子チームあり）。
     *
     * @var array<string, array<string, array<string, string|list<string>>>>
     */
    private const TEAM_RULES = [
        '大阪' => [
            'GR-C部' => [
                '総務課' => 'GR-総務課_大阪',
                '教育課' => 'GR-教育課_大阪',
            ],
            'GR-S部' => [
                '1課' => 'GR-S1課_大阪',
                '2課' => 'GR-S2課_大阪',
                '3課' => 'GR-S3課_大阪',
            ],
            'GR-M部' => [
                '1課' => 'GR-M1課_大阪',
                '2課' => 'GR-M2課_大阪',
            ],
            'GR-O部' => [
                '送迎課' => 'GR-O 送迎課_大阪',
                'CS課' => [
                    'parent' => 'GR-O CS課_大阪',
                    'children' => [
                        '固定現場チーム' => 'GR-O CS課 固定現場チーム_大阪',
                        'エリア担当チーム' => 'GR-O CS課 エリア担当チーム_大阪',
                    ],
                ],
            ],
        ],
        '福岡' => [
            'GR-C部' => [
                '総務課' => 'GR-C 総務課_福岡',
                '教育課' => 'GR-C 教育課_福岡',
            ],
            'GR-S部' => [
                '1課' => 'GR-S1課_福岡',
                '2課' => 'GR-S2課_福岡',
                '3課' => 'GR-S3課_福岡',
            ],
            'GR-O部' => [
                '送迎課' => 'GR-O 送迎課_福岡',
                'CS課' => [
                    'parent' => 'GR-O CS課_福岡',
                    'children' => [
                        '固定現場チーム' => 'GR-O CS課 固定現場チーム_福岡',
                        'エリア担当チーム' => 'GR-O CS課 エリア担当チーム_福岡',
                    ],
                ],
            ],
        ],
        '東京' => [
            'GR-C部' => [
                '総務課' => 'GR-C総務課_東京',
                '教育課' => 'GR-C教育課_東京',
            ],
            'GR-S部' => [
                '1課' => 'GR-S1課_東京',
                '2課' => 'GR-S2課_東京',
                '3課' => 'GR-S3課_東京',
            ],
            'GR-M部' => [
                '1課' => 'GR-M1課_東京',
                '2課' => 'GR-M2課_東京',
                '3課' => 'GR-M3課_東京',
            ],
            'GR-O部' => [
                '送迎課' => 'GR-O 送迎課_東京',
                'CS課' => [
                    'parent' => 'GR-O CS課_東京',
                    'children' => [
                        '固定現場チーム' => 'GR-O CS課 固定現場チーム_東京',
                        'エリア担当チーム' => 'GR-O CS課 エリア担当チーム_東京',
                    ],
                ],
            ],
        ],
        '名古屋' => [
            'GR-C部' => [
                '総務課' => 'GR-C総務課_名古屋',
                '教育課' => 'GR-C教育課_名古屋',
            ],
            'GR-S部' => [
                '1課' => 'GR-S1課_名古屋',
                '2課' => 'GR-S2課_名古屋',
            ],
            'GR-M部' => [
                '1課' => 'GR-M1課_名古屋',
                '2課' => 'GR-M2課_名古屋',
            ],
            'GR-O部' => [
                '送迎課' => 'GR-O 送迎課_名古屋',
                'CS課' => [
                    'parent' => 'GR-O CS課_名古屋',
                    'children' => [
                        '固定現場チーム' => 'GR-O CS課 固定現場チーム_名古屋',
                        'エリア担当チーム' => 'GR-O CS課 エリア担当チーム_名古屋',
                    ],
                ],
            ],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function sectionOptionsFor(?string $location): array
    {
        $location = trim((string) $location);

        if ($location === '' || ! isset(self::TEAM_RULES[$location])) {
            return [];
        }

        return self::DIVISIONS;
    }

    /**
     * @return list<string>
     */
    public static function teamOptionsFor(?string $location, ?string $division): array
    {
        $rules = self::divisionRules(trim((string) $location), trim((string) $division));

        if ($rules === null) {
            return [];
        }

        return array_keys($rules);
    }

    /**
     * @return list<string>
     */
    public static function teamChildOptionsFor(?string $location, ?string $division, ?string $parentLabel): array
    {
        $rules = self::divisionRules(trim((string) $location), trim((string) $division));
        $parentLabel = trim((string) $parentLabel);

        if ($rules === null || $parentLabel === '' || ! isset($rules[$parentLabel])) {
            return [];
        }

        $rule = $rules[$parentLabel];

        if (! is_array($rule) || ! isset($rule['children'])) {
            return [];
        }

        return array_keys($rule['children']);
    }

    /**
     * @return list<string>
     */
    public static function validTeamLabelsFor(?string $location, ?string $division): array
    {
        $rules = self::divisionRules(trim((string) $location), trim((string) $division));

        if ($rules === null) {
            return [];
        }

        $labels = [];

        foreach ($rules as $label => $rule) {
            if (is_string($rule)) {
                $labels[] = $label;

                continue;
            }

            $labels[] = $label;
            foreach (array_keys($rule['children'] ?? []) as $childLabel) {
                $labels[] = $childLabel;
            }
        }

        return $labels;
    }

    public static function resolveSectionToCanonical(?string $division, ?string $location): ?string
    {
        $division = trim((string) $division);
        $location = trim((string) $location);

        if ($division === '' || $location === '') {
            return null;
        }

        if (in_array($division, self::DIVISIONS, true)) {
            return self::canonicalSection($division, $location);
        }

        if (self::isCanonicalSection($division, $location)) {
            return $division;
        }

        return null;
    }

    public static function resolveTeamToCanonical(
        ?string $location,
        ?string $division,
        ?string $teamLabel,
    ): ?string {
        $teamLabel = trim((string) $teamLabel);

        if ($teamLabel === '') {
            return null;
        }

        $rules = self::divisionRules(trim((string) $location), trim((string) $division));

        if ($rules === null) {
            return $teamLabel;
        }

        foreach ($rules as $label => $rule) {
            if (is_string($rule)) {
                if ($teamLabel === $label || $teamLabel === $rule) {
                    return $rule;
                }

                continue;
            }

            if ($teamLabel === $label) {
                return $rule['parent'] ?? null;
            }

            foreach ($rule['children'] ?? [] as $childLabel => $canonical) {
                if ($teamLabel === $childLabel || $teamLabel === $canonical) {
                    return $canonical;
                }
            }
        }

        return $teamLabel;
    }

    /**
     * @return array{section: ?string, team: ?string}
     */
    public static function toFormValues(?string $location, ?string $canonicalSection, ?string $canonicalTeam): array
    {
        $location = trim((string) $location);
        $division = self::divisionFromCanonicalSection($canonicalSection, $location);
        $teamLabel = self::teamLabelFromCanonical($location, $division, $canonicalTeam);

        return [
            'section' => $division,
            'team' => $teamLabel,
        ];
    }

    /**
     * @return array{
     *     sections: array<string, list<string>>,
     *     teams: array<string, array<string, array<string, string|array{parent: string, children: array<string, string>}>>>
     * }
     */
    public static function clientMap(): array
    {
        $sections = [];

        foreach (array_keys(self::TEAM_RULES) as $location) {
            $sections[$location] = self::DIVISIONS;
        }

        return [
            'sections' => $sections,
            'teams' => self::TEAM_RULES,
        ];
    }

    public static function isCanonicalSection(string $value, string $location): bool
    {
        foreach (self::DIVISIONS as $division) {
            if ($value === self::canonicalSection($division, $location)) {
                return true;
            }
        }

        return false;
    }

    public static function canonicalSection(string $division, string $location): string
    {
        $prefix = match ($division) {
            'GR-C部' => 'GR-C',
            'GR-S部' => 'GR-S',
            'GR-M部' => 'GR-M',
            'GR-O部' => 'GR-O',
            default => str_replace('部', '', $division),
        };

        return $prefix.'_'.$location;
    }

    private static function divisionFromCanonicalSection(?string $canonicalSection, string $location): ?string
    {
        $canonicalSection = trim((string) $canonicalSection);

        if ($canonicalSection === '') {
            return null;
        }

        foreach (self::DIVISIONS as $division) {
            if ($canonicalSection === self::canonicalSection($division, $location)) {
                return $division;
            }
        }

        return $canonicalSection;
    }

    private static function teamLabelFromCanonical(?string $location, ?string $division, ?string $canonicalTeam): ?string
    {
        $canonicalTeam = trim((string) $canonicalTeam);

        if ($canonicalTeam === '' || $division === null) {
            return null;
        }

        $rules = self::divisionRules(trim((string) $location), trim((string) $division));

        if ($rules === null) {
            return $canonicalTeam;
        }

        foreach ($rules as $label => $rule) {
            if (is_string($rule)) {
                if ($canonicalTeam === $rule) {
                    return $label;
                }

                continue;
            }

            if ($canonicalTeam === ($rule['parent'] ?? null)) {
                return $label;
            }

            foreach ($rule['children'] ?? [] as $childLabel => $childCanonical) {
                if ($canonicalTeam === $childCanonical) {
                    return $childLabel;
                }
            }
        }

        return $canonicalTeam;
    }

    /**
     * @return array<string, string|array{parent: string, children: array<string, string>}>|null
     */
    private static function divisionRules(string $location, string $division): ?array
    {
        if ($location === '' || $division === '') {
            return null;
        }

        return self::TEAM_RULES[$location][$division] ?? null;
    }
}
