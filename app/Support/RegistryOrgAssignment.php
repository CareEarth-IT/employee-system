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

        if (RegistryTeamByAssignment::isDepartmentTeamOnly($department)) {
            return [$sectionValue, $teamValue, $teamValue];
        }

        return [$sectionValue, $teamValue, $sectionValue];
    }

    /**
     * @return array{department_primary: ?string, section_primary: ?string}
     */
    public static function hrDetailPrimaryFromAffiliation(
        ?string $department,
        ?string $location,
        ?string $combinedSection,
    ): array {
        $department = trim((string) $department);
        $location = trim((string) $location);
        $combinedSection = trim((string) $combinedSection);

        $split = RegistryTeamByAssignment::splitStoredAssignment(
            $combinedSection !== '' ? $combinedSection : null,
            $department !== '' ? $department : null,
            $location !== '' ? $location : null,
        );

        [, , $sectionPrimary] = self::resolveForStorage(
            $department,
            $location,
            $split['section'],
            $split['team'],
        );

        return [
            'department_primary' => $department !== '' ? $department : null,
            'section_primary' => $sectionPrimary,
        ];
    }
}
