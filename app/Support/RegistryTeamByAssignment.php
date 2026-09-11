<?php

namespace App\Support;

class RegistryTeamByAssignment
{
    /** @var array<string, list<string>> */
    private const SECTION_TEAMS = [
        'Food Sales部' => ['法人チーム', 'ECチーム'],
        'Food Logistic部' => ['運送チーム', '出荷チーム'],
    ];

    /** @var list<string> */
    private const SECTION_TEAM_ONLY = [
        'Food Sales部',
        'Food Logistic部',
    ];

    /** @var array<string, list<string>> 旧所属部署名（既存データ互換） */
    private const LEGACY_DEPARTMENT_TEAMS = [
        'Food Sales部' => ['法人チーム', 'ECチーム'],
        'Food Logistic部' => ['運送チーム', '出荷チーム'],
    ];

    /** @var list<string> */
    private const LEGACY_DEPARTMENT_TEAM_ONLY = [
        'Food Sales部',
        'Food Logistic部',
    ];

    /**
     * @return list<string>
     */
    public static function optionsFor(?string $department, ?string $location, ?string $section): array
    {
        $department = trim((string) $department);
        $location = trim((string) $location);
        $section = trim((string) $section);

        if ($department === RegistryDepartmentOptions::FOOD_DEPARTMENT && $section !== '') {
            return self::SECTION_TEAMS[$section] ?? [];
        }

        if (isset(self::LEGACY_DEPARTMENT_TEAMS[$department])) {
            return self::LEGACY_DEPARTMENT_TEAMS[$department];
        }

        if ($department === '' || $department !== RegistryGrAssignment::DEPARTMENT || $location === '' || $section === '') {
            return [];
        }

        return RegistryGrAssignment::teamOptionsFor($location, $section);
    }

    /**
     * @return list<string>
     */
    public static function childOptionsFor(
        ?string $department,
        ?string $location,
        ?string $section,
        ?string $parentTeam,
    ): array {
        if (trim((string) $department) !== RegistryGrAssignment::DEPARTMENT) {
            return [];
        }

        return RegistryGrAssignment::teamChildOptionsFor($location, $section, $parentTeam);
    }

    /**
     * @return list<string>
     */
    public static function validValuesFor(?string $department, ?string $location, ?string $section): array
    {
        $department = trim((string) $department);
        $location = trim((string) $location);
        $section = trim((string) $section);

        if ($department === RegistryDepartmentOptions::FOOD_DEPARTMENT && $section !== '') {
            return self::SECTION_TEAMS[$section] ?? [];
        }

        if (isset(self::LEGACY_DEPARTMENT_TEAMS[$department])) {
            return self::LEGACY_DEPARTMENT_TEAMS[$department];
        }

        if ($department !== RegistryGrAssignment::DEPARTMENT || $location === '' || $section === '') {
            return [];
        }

        return RegistryGrAssignment::validTeamLabelsFor($location, $section);
    }

    public static function isValidTeam(
        ?string $department,
        ?string $location,
        ?string $section,
        ?string $team,
    ): bool {
        $team = trim((string) $team);

        if ($team === '') {
            return true;
        }

        return in_array($team, self::validValuesFor($department, $location, $section), true);
    }

    public static function hasRules(?string $department): bool
    {
        $department = trim((string) $department);

        return $department === RegistryDepartmentOptions::FOOD_DEPARTMENT
            || isset(self::LEGACY_DEPARTMENT_TEAMS[$department])
            || $department === RegistryGrAssignment::DEPARTMENT;
    }

    public static function shouldShowField(
        ?string $department,
        ?string $location = null,
        ?string $section = null,
        ?string $current = null,
    ): bool {
        return self::forSelect($department, $location, $section, $current) !== [];
    }

    /** 旧所属部署名（Food Sales部 など）向け */
    public static function isDepartmentTeamOnly(?string $department): bool
    {
        return in_array(trim((string) $department), self::LEGACY_DEPARTMENT_TEAM_ONLY, true);
    }

    public static function isSectionTeamOnly(?string $department, ?string $section): bool
    {
        if (trim((string) $department) === RegistryDepartmentOptions::FOOD_DEPARTMENT) {
            return in_array(trim((string) $section), self::SECTION_TEAM_ONLY, true);
        }

        return self::isDepartmentTeamOnly($department);
    }

    public static function requiresSection(?string $department): bool
    {
        return trim((string) $department) === RegistryGrAssignment::DEPARTMENT;
    }

    /**
     * @return list<string>
     */
    public static function forSelect(
        ?string $department,
        ?string $location,
        ?string $section,
        ?string $current = null,
    ): array {
        $options = self::optionsFor($department, $location, $section);
        $valid = self::validValuesFor($department, $location, $section);

        if ($current === null || $current === '') {
            return $options;
        }

        if (in_array($current, $valid, true) && ! in_array($current, $options, true)) {
            return [...$options, $current];
        }

        if (! in_array($current, $options, true)) {
            return [...$options, $current];
        }

        return $options;
    }

    /**
     * @return array{section: ?string, team: ?string}
     */
    public static function splitStoredAssignment(
        ?string $combined,
        ?string $department = null,
        ?string $location = null,
    ): array {
        $combined = trim((string) $combined);

        if ($combined === '') {
            return ['section' => null, 'team' => null];
        }

        if (! str_contains($combined, ',')) {
            if ($department === RegistryGrAssignment::DEPARTMENT) {
                return RegistryGrAssignment::parseStoredAssignment($location, $combined);
            }

            if (RegistryTeamOptions::isTeam($combined)) {
                return ['section' => null, 'team' => $combined];
            }

            return ['section' => $combined, 'team' => null];
        }

        [$canonicalSection, $canonicalTeam] = array_map('trim', explode(',', $combined, 2));

        if ($department === RegistryGrAssignment::DEPARTMENT) {
            return RegistryGrAssignment::parseStoredAssignment(
                $location,
                RegistryOrgAssignment::combine($canonicalSection, $canonicalTeam),
            );
        }

        return [
            'section' => $canonicalSection !== '' ? $canonicalSection : null,
            'team' => $canonicalTeam !== '' ? $canonicalTeam : null,
        ];
    }

    /**
     * @return array{
     *     departmentTeams: array<string, list<string>>,
     *     departmentTeamOnly: list<string>,
     *     sectionTeams: array<string, list<string>>,
     *     sectionTeamOnly: list<string>,
     *     gr: array{sections: array<string, list<string>>, teams: array<string, array<string, array<string, string|array{parent: string, children: array<string, string>}>>>}
     * }
     */
    public static function clientMap(): array
    {
        return [
            'departmentTeams' => self::LEGACY_DEPARTMENT_TEAMS,
            'departmentTeamOnly' => self::LEGACY_DEPARTMENT_TEAM_ONLY,
            'sectionTeams' => self::SECTION_TEAMS,
            'sectionTeamOnly' => self::SECTION_TEAM_ONLY,
            'gr' => RegistryGrAssignment::clientMap(),
        ];
    }
}
