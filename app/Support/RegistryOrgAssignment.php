<?php

namespace App\Support;

class RegistryOrgAssignment
{
    /**
     * @return array{section: ?string, team: ?string}
     */
    public static function split(?string $combined): array
    {
        $combined = trim((string) $combined);

        if ($combined === '') {
            return ['section' => null, 'team' => null];
        }

        $parts = array_values(array_filter(
            array_map('trim', explode(',', $combined)),
            static fn (string $part): bool => $part !== '',
        ));

        if ($parts === []) {
            return ['section' => null, 'team' => null];
        }

        if (count($parts) === 1) {
            $part = $parts[0];

            if (RegistryTeamOptions::isTeam($part)) {
                return ['section' => null, 'team' => $part];
            }

            return ['section' => $part, 'team' => null];
        }

        $section = null;
        $team = null;

        foreach ($parts as $part) {
            if (RegistryTeamOptions::isTeam($part)) {
                $team = $part;

                continue;
            }

            $section = $part;
        }

        return ['section' => $section, 'team' => $team];
    }

    public static function combine(?string $section, ?string $team): ?string
    {
        $parts = [];

        foreach ([$section, $team] as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return $parts === [] ? null : implode(',', $parts);
    }

    /**
     * @return array{section: ?string, team: ?string}
     */
    public static function splitForRegistryForm(
        ?string $combined,
        ?string $department = null,
        ?string $location = null,
    ): array {
        return RegistryTeamByAssignment::splitStoredAssignment($combined, $department, $location);
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    public static function resolveForStorage(
        ?string $department,
        ?string $location,
        ?string $section,
        ?string $team,
    ): array {
        $department = trim((string) $department);
        $location = trim((string) $location);
        $section = trim((string) ($section ?? ''));
        $team = trim((string) ($team ?? ''));

        if ($department === RegistryGrAssignment::DEPARTMENT) {
            $canonicalSection = RegistryGrAssignment::resolveSectionToCanonical($section, $location);
            $canonicalTeam = RegistryGrAssignment::resolveTeamToCanonical($location, $section, $team);

            return [$canonicalSection, $canonicalTeam, $canonicalSection];
        }

        $sectionValue = $section !== '' ? $section : null;
        $teamValue = $team !== '' ? $team : null;

        if (RegistryTeamByAssignment::isSectionTeamOnly($department, $section)) {
            return [$sectionValue, $teamValue, self::combine($sectionValue, $teamValue) ?? $sectionValue];
        }

        if (RegistryTeamByAssignment::isDepartmentTeamOnly($department)) {
            return [$sectionValue, $teamValue, $teamValue];
        }

        return [$sectionValue, $teamValue, self::combine($sectionValue, $teamValue) ?? $sectionValue];
    }

    /**
     * 部署・管轄・課/チームの保存値を正規化する。
     *
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    public static function normalizePrimaryOrgStorage(
        ?string $department,
        ?string $jurisdiction,
        ?string $sectionPrimary,
    ): array {
        [$department, $jurisdiction] = RegistryGrAssignment::normalizeOrgStorage(
            $department,
            $jurisdiction,
            $sectionPrimary,
        );

        $department = trim((string) $department);
        $jurisdiction = trim((string) $jurisdiction);
        $sectionPrimary = trim((string) $sectionPrimary);

        if ($department === '' || $department !== RegistryGrAssignment::DEPARTMENT || str_contains($department, ',')) {
            return [
                $department !== '' ? $department : null,
                $jurisdiction !== '' ? $jurisdiction : null,
                $sectionPrimary !== '' ? $sectionPrimary : null,
            ];
        }

        if ($jurisdiction === '' && $sectionPrimary !== '') {
            foreach (['大阪', '東京', '名古屋', '福岡'] as $location) {
                if (str_contains($sectionPrimary, $location) || preg_match('/_'.$location.'(?:$|,)/u', $sectionPrimary) === 1) {
                    $jurisdiction = $location;
                    break;
                }
            }
        }

        $split = self::splitForRegistryForm(
            $sectionPrimary !== '' ? $sectionPrimary : null,
            $department,
            $jurisdiction !== '' ? $jurisdiction : null,
        );

        [$sectionStored, $teamStored] = array_slice(
            self::resolveForStorage(
                $department,
                $jurisdiction,
                $split['section'],
                $split['team'],
            ),
            0,
            2,
        );

        return [
            $department,
            $jurisdiction !== '' ? $jurisdiction : null,
            self::preserveSectionWhenNormalizationFails($sectionPrimary, $sectionStored, $teamStored),
        ];
    }

    private static function preserveSectionWhenNormalizationFails(
        string $originalSectionPrimary,
        ?string $sectionStored,
        ?string $teamStored,
    ): ?string {
        $normalizedSection = self::combine($sectionStored, $teamStored) ?? $sectionStored;

        if ($originalSectionPrimary === '') {
            return $normalizedSection;
        }

        if ($normalizedSection === null || $normalizedSection === '') {
            return $originalSectionPrimary;
        }

        $originalParts = count(array_filter(array_map('trim', explode(',', $originalSectionPrimary))));
        $normalizedParts = count(array_filter(array_map('trim', explode(',', $normalizedSection))));

        if ($originalParts > max(2, $normalizedParts + 1)) {
            return $originalSectionPrimary;
        }

        return $normalizedSection;
    }

    /**
     * @return array{department_primary: ?string, section_primary: ?string, jurisdiction: ?string}
     */
    public static function hrDetailPrimaryFromAffiliation(
        ?string $department,
        ?string $location,
        ?string $combinedSection,
    ): array {
        [$departmentPrimary, $jurisdiction, $sectionPrimary] = self::normalizePrimaryOrgStorage(
            $department,
            $location,
            $combinedSection,
        );

        return [
            'department_primary' => $departmentPrimary,
            'section_primary' => $sectionPrimary,
            'jurisdiction' => $jurisdiction,
        ];
    }
}
