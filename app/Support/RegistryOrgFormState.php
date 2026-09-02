<?php

namespace App\Support;

use App\Models\User;

class RegistryOrgFormState
{
    /**
     * @param  array{
     *     company?: string|null,
     *     location?: string|null,
     *     department?: string|null,
     *     section?: string|null,
     *     team?: string|null,
     * }  $values
     * @return array{
     *     companyOptions: list<string>,
     *     selectedCompany: string,
     *     departmentOptions: list<string>,
     *     selectedDepartment: string,
     *     selectedLocation: string,
     *     sectionOptions: list<string>,
     *     selectedSection: string,
     *     selectedTeam: string,
     *     teamOptions: list<string>,
     *     sectionRequiresLocation: bool,
     *     sectionHasRules: bool,
     *     sectionSelectDisabled: bool,
     *     showTeamField: bool,
     * }
     */
    public static function fromValues(array $values, bool $splitSectionTeam = false): array
    {
        $selectedDepartment = trim((string) ($values['department'] ?? ''));
        $selectedLocation = trim((string) ($values['location'] ?? ''));
        $storedSection = trim((string) ($values['stored_section'] ?? ($values['section'] ?? '')));
        $selectedSection = $splitSectionTeam
            ? trim((string) ($values['section'] ?? ''))
            : $storedSection;
        $selectedTeam = $splitSectionTeam
            ? trim((string) ($values['team'] ?? ''))
            : '';

        $sectionOptions = $splitSectionTeam
            ? RegistrySectionByAssignment::forSelect(
                $selectedDepartment,
                $selectedLocation,
                $selectedSection !== '' ? $selectedSection : null,
            )
            : self::combinedSectionOptions(
                $selectedDepartment,
                $selectedLocation,
                $selectedSection !== '' ? $selectedSection : null,
            );
        $sectionRequiresLocation = RegistrySectionByAssignment::requiresLocation($selectedDepartment);
        $sectionHasRules = RegistrySectionByAssignment::hasRules($selectedDepartment);
        $sectionSelectDisabled = ($sectionHasRules && $sectionRequiresLocation && $selectedLocation === '')
            || ($sectionHasRules && $sectionOptions === [] && $selectedSection === '');

        return [
            'companyOptions' => User::companyOptions($values['company'] ?? null),
            'selectedCompany' => trim((string) ($values['company'] ?? '')),
            'departmentOptions' => User::registryDepartmentOptions($selectedDepartment !== '' ? $selectedDepartment : null),
            'selectedDepartment' => $selectedDepartment,
            'selectedLocation' => $selectedLocation,
            'sectionOptions' => $sectionOptions,
            'selectedSection' => $selectedSection,
            'selectedTeam' => $selectedTeam,
            'teamOptions' => RegistryTeamByAssignment::forSelect(
                $selectedDepartment,
                $selectedLocation,
                $selectedSection,
                $selectedTeam !== '' ? $selectedTeam : null,
            ),
            'sectionRequiresLocation' => $sectionRequiresLocation,
            'sectionHasRules' => $sectionHasRules,
            'sectionSelectDisabled' => $sectionSelectDisabled,
            'showTeamField' => $splitSectionTeam && RegistryTeamByAssignment::shouldShowField(
                $selectedDepartment,
                $selectedLocation,
                $selectedSection !== '' ? $selectedSection : null,
                $selectedTeam !== '' ? $selectedTeam : null,
            ),
        ];
    }

    /**
     * @return list<string>
     */
    public static function combinedSectionOptions(
        ?string $department,
        ?string $location,
        ?string $current = null,
    ): array {
        $department = trim((string) $department);
        $location = trim((string) $location);
        $current = trim((string) ($current ?? ''));

        $options = RegistrySectionByAssignment::forSelect(
            $department,
            $location,
            $current !== '' ? $current : null,
        );

        if (RegistryTeamByAssignment::isDepartmentTeamOnly($department)) {
            $options = array_values(array_unique([
                ...$options,
                ...RegistryTeamByAssignment::optionsFor($department, $location, ''),
            ]));
        }

        if ($current !== '' && ! in_array($current, $options, true)) {
            $options[] = $current;
        }

        return $options;
    }
}
