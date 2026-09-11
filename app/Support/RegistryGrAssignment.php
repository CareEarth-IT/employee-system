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
     * 社員名簿・詳細CSV向けの部署表示（GR 部は「管轄＋グローバル事業部」）。
     */
    public static function rosterDepartmentLabel(?string $jurisdiction, ?string $departmentPrimary): string
    {
        [$departmentPrimary, $jurisdiction] = self::normalizeOrgStorage($departmentPrimary, $jurisdiction);

        $departmentPrimary = trim((string) $departmentPrimary);
        $jurisdiction = trim((string) $jurisdiction);

        if ($departmentPrimary === '') {
            return '';
        }

        if (str_ends_with($departmentPrimary, 'グローバル事業部')) {
            return $departmentPrimary;
        }

        if ($departmentPrimary !== self::DEPARTMENT) {
            return $departmentPrimary;
        }

        if ($jurisdiction === '') {
            return $departmentPrimary;
        }

        return $jurisdiction.'グローバル事業部';
    }

    /**
     * HR詳細・所属の保存形式を GR 部含め正規化する。
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function normalizeOrgStorage(?string $department, ?string $jurisdiction, ?string $section = null): array
    {
        $department = trim((string) $department);
        $jurisdiction = trim((string) $jurisdiction);
        $section = trim((string) $section);

        if ($department === '') {
            return [null, $jurisdiction !== '' ? $jurisdiction : null];
        }

        if (str_contains($department, ',')) {
            return self::normalizeCommaSeparatedDepartment($department, $jurisdiction, $section);
        }

        return self::normalizeSingleDepartment($department, $jurisdiction, $section);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function normalizeSingleDepartment(string $department, string $jurisdiction, string $section): array
    {
        foreach (self::jurisdictions() as $location) {
            if ($department === $location.'グローバル事業部') {
                return [self::DEPARTMENT, $location];
            }

            if ($department === $location.'-GR部' || $department === $location.'‐GR部') {
                return [self::DEPARTMENT, $location];
            }
        }

        if ($department === self::DEPARTMENT) {
            $jurisdiction = self::inferJurisdiction($department, $jurisdiction, $section) ?? $jurisdiction;

            return [self::DEPARTMENT, $jurisdiction];
        }

        return [$department, $jurisdiction];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function normalizeCommaSeparatedDepartment(string $department, string $jurisdiction, string $section): array
    {
        $parts = array_values(array_filter(
            array_map('trim', explode(',', $department)),
            static fn (string $part): bool => $part !== '',
        ));

        $normalizedParts = [];
        $inferredJurisdiction = $jurisdiction;

        foreach ($parts as $part) {
            [$normalizedPart, $partJurisdiction] = self::normalizeSingleDepartment($part, $inferredJurisdiction, $section);
            $normalizedParts[] = $normalizedPart;

            if ($inferredJurisdiction === '' && $partJurisdiction !== '') {
                $inferredJurisdiction = $partJurisdiction;
            }
        }

        return [implode(',', $normalizedParts), $inferredJurisdiction];
    }

    private static function inferJurisdiction(string $department, string $jurisdiction, string $section): ?string
    {
        if ($jurisdiction !== '') {
            return $jurisdiction;
        }

        foreach (self::jurisdictions() as $location) {
            if (str_contains($department, $location) || str_contains($section, $location)) {
                return $location;
            }
        }

        if (preg_match('/_(大阪|東京|名古屋|福岡)(?:$|,)/u', $section, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/_(大阪|東京|名古屋|福岡)$/u', $section, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function jurisdictions(): array
    {
        return ['大阪', '東京', '名古屋', '福岡'];
    }

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
     * 保存済みの GR 課・チーム値（旧形式含む）をフォーム用の課・チームへ分解する。
     *
     * @return array{section: ?string, team: ?string}
     */
    public static function parseStoredAssignment(?string $location, ?string $combined): array
    {
        $combined = trim((string) $combined);

        if ($combined === '') {
            return ['section' => null, 'team' => null];
        }

        $location = trim((string) $location);

        if ($location === '') {
            $location = self::inferLocationFromStoredValue($combined) ?? '';
        }

        if (str_contains($combined, ',')) {
            $parts = array_values(array_filter(
                array_map('trim', explode(',', $combined)),
                static fn (string $part): bool => $part !== '',
            ));

            return self::parseStoredAssignmentParts($location, $parts, $combined);
        }

        $parsed = self::parseStoredPart($location, $combined);

        if ($parsed['team'] !== null && $parsed['section'] === null && $location !== '') {
            $division = self::divisionFromTeamCanonical($location, $parsed['team']);

            if ($division !== null) {
                $parsed['section'] = self::canonicalSection($division, $location);
            }
        }

        return self::toFormValues($location, $parsed['section'], $parsed['team']);
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

    /**
     * @param  list<string>  $parts
     * @return array{section: ?string, team: ?string}
     */
    private static function parseStoredAssignmentParts(string $location, array $parts, string $combined): array
    {
        $sectionCanonical = null;
        $teamCandidates = [];
        $unknownParts = [];

        foreach ($parts as $part) {
            if ($location !== '' && self::isCanonicalSection($part, $location)) {
                $sectionCanonical = $part;

                continue;
            }

            $parsed = self::parseStoredPart($location, $part);

            if ($parsed['team'] !== null) {
                $teamCandidates[] = $parsed['team'];

                if ($sectionCanonical === null && $parsed['section'] !== null) {
                    $sectionCanonical = $parsed['section'];
                }

                continue;
            }

            if ($parsed['section'] !== null && $location !== '' && self::isCanonicalSection($parsed['section'], $location)) {
                $sectionCanonical = $parsed['section'];

                continue;
            }

            $unknownParts[] = $part;
        }

        if ($unknownParts !== []) {
            return ['section' => $combined, 'team' => null];
        }

        $teamCanonical = self::pickBestTeamCandidate($teamCandidates);

        if ($teamCanonical === null && count(array_unique($teamCandidates)) > 1) {
            return ['section' => $combined, 'team' => null];
        }

        if ($sectionCanonical === null && $teamCanonical !== null && $location !== '') {
            $division = self::divisionFromTeamCanonical($location, $teamCanonical);

            if ($division !== null) {
                $sectionCanonical = self::canonicalSection($division, $location);
            }
        }

        return self::toFormValues($location, $sectionCanonical, $teamCanonical);
    }

    /**
     * @param  list<string>  $teams
     */
    private static function pickBestTeamCandidate(array $teams): ?string
    {
        $teams = array_values(array_unique(array_filter(
            $teams,
            static fn (string $team): bool => trim($team) !== '',
        )));

        if ($teams === []) {
            return null;
        }

        if (count($teams) === 1) {
            return $teams[0];
        }

        $childTeams = array_values(array_filter(
            $teams,
            static fn (string $team): bool => str_contains($team, '固定現場') || str_contains($team, 'エリア担当'),
        ));

        if (count($childTeams) === 1) {
            return $childTeams[0];
        }

        if (count($childTeams) > 1) {
            return null;
        }

        return null;
    }

    /**
     * @return array{section: ?string, team: ?string}
     */
    private static function parseStoredPart(string $location, string $part): array
    {
        if ($location !== '') {
            foreach (self::DIVISIONS as $division) {
                if ($part === self::canonicalSection($division, $location) || $part === $division) {
                    return [
                        'section' => self::canonicalSection($division, $location),
                        'team' => null,
                    ];
                }
            }
        }

        foreach (self::jurisdictions() as $candidateLocation) {
            if ($location !== '' && $location !== $candidateLocation) {
                continue;
            }

            foreach (self::DIVISIONS as $division) {
                $rules = self::TEAM_RULES[$candidateLocation][$division] ?? null;

                if ($rules === null) {
                    continue;
                }

                foreach ($rules as $label => $rule) {
                    if (is_string($rule)) {
                        if (self::storedValuesMatch($part, $rule) || self::storedValuesMatch($part, $label)) {
                            return [
                                'section' => self::canonicalSection($division, $candidateLocation),
                                'team' => $rule,
                            ];
                        }

                        continue;
                    }

                    $parent = $rule['parent'] ?? null;

                    if ($parent !== null && (self::storedValuesMatch($part, $parent) || self::storedValuesMatch($part, $label))) {
                        return [
                            'section' => self::canonicalSection($division, $candidateLocation),
                            'team' => $parent,
                        ];
                    }

                    foreach ($rule['children'] ?? [] as $childLabel => $childCanonical) {
                        if (self::storedValuesMatch($part, $childCanonical) || self::storedValuesMatch($part, $childLabel)) {
                            return [
                                'section' => self::canonicalSection($division, $candidateLocation),
                                'team' => $childCanonical,
                            ];
                        }
                    }
                }
            }
        }

        if ($location !== '' && self::isCanonicalSection($part, $location)) {
            return ['section' => $part, 'team' => null];
        }

        return ['section' => $part, 'team' => null];
    }

    private static function storedValuesMatch(string $stored, string $reference): bool
    {
        if ($stored === $reference) {
            return true;
        }

        return self::normalizeComparable($stored) === self::normalizeComparable($reference);
    }

    private static function normalizeComparable(string $value): string
    {
        return preg_replace('/\s+/u', '', $value) ?? $value;
    }

    private static function inferLocationFromStoredValue(string $value): ?string
    {
        if (preg_match('/_(大阪|東京|名古屋|福岡)(?:$|,)/u', $value, $matches) === 1) {
            return $matches[1];
        }

        foreach (self::jurisdictions() as $location) {
            if (str_contains($value, $location)) {
                return $location;
            }
        }

        return null;
    }

    private static function divisionFromTeamCanonical(string $location, string $teamCanonical): ?string
    {
        foreach (self::DIVISIONS as $division) {
            $rules = self::TEAM_RULES[$location][$division] ?? null;

            if ($rules === null) {
                continue;
            }

            foreach ($rules as $rule) {
                if (is_string($rule) && self::storedValuesMatch($teamCanonical, $rule)) {
                    return $division;
                }

                if (! is_array($rule)) {
                    continue;
                }

                if (isset($rule['parent']) && self::storedValuesMatch($teamCanonical, $rule['parent'])) {
                    return $division;
                }

                foreach ($rule['children'] ?? [] as $childCanonical) {
                    if (self::storedValuesMatch($teamCanonical, $childCanonical)) {
                        return $division;
                    }
                }
            }
        }

        return null;
    }
}
