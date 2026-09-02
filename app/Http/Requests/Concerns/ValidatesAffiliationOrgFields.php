<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use App\Support\RegistryDepartmentOptions;
use App\Support\RegistryOrgAssignment;
use App\Support\RegistryOrgFormState;
use Illuminate\Validation\Rule;

trait ValidatesAffiliationOrgFields
{
    protected function mergeAffiliationOrgInputForValidation(): void
    {
        $nullable = static fn (mixed $value): ?string => (($text = trim((string) $value)) !== '' ? $text : null);

        $this->merge([
            'company' => $nullable($this->input('company', '')),
            'location' => $nullable($this->input('location', '')),
            'department' => $nullable($this->input('department', '')),
            'section' => $nullable($this->input('section', '')),
            'team' => $nullable($this->input('team', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function affiliationOrgFieldRules(
        ?string $currentDepartment = null,
        ?string $currentSection = null,
        ?string $currentTeam = null,
    ): array {
        $departmentCurrent = (string) ($this->input('department') ?: $currentDepartment ?: '');
        $sectionCurrent = (string) ($this->input('section') ?: $currentSection ?: '');
        $teamCurrent = (string) ($this->input('team') ?: $currentTeam ?: '');

        return [
            'company' => ['nullable', 'string', Rule::in(User::companyOptions($this->input('company')))],
            'location' => ['nullable', 'string', Rule::in(User::OFFICE_LOCATIONS)],
            'department' => ['nullable', 'string', Rule::in(User::registryDepartmentOptions($departmentCurrent ?: null))],
            'section' => [
                'nullable',
                'string',
                Rule::in(RegistryOrgFormState::combinedSectionOptions(
                    $departmentCurrent,
                    (string) $this->input('location', ''),
                    $sectionCurrent !== '' ? $sectionCurrent : null,
                )),
            ],
            'team' => ['nullable'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowedAffiliationTeamValues(?string $currentTeam = null): array
    {
        $allowed = \App\Support\RegistryTeamByAssignment::validValuesFor(
            (string) $this->input('department', ''),
            (string) $this->input('location', ''),
            (string) $this->input('section', ''),
        );

        if ($currentTeam !== null && $currentTeam !== '' && ! in_array($currentTeam, $allowed, true)) {
            $allowed[] = $currentTeam;
        }

        return $allowed;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function resolveAffiliationOrgForStorage(array $validated): array
    {
        $registryDepartment = trim((string) ($validated['department'] ?? ''));
        $location = trim((string) ($validated['location'] ?? ''));
        $section = trim((string) ($validated['section'] ?? ''));
        $team = trim((string) ($validated['team'] ?? ''));

        if ($registryDepartment === '' && $section === '' && $team === '') {
            $validated['department'] = null;
            $validated['section'] = null;

            return $validated;
        }

        $affiliationOrg = RegistryDepartmentOptions::resolveAffiliation(
            $registryDepartment,
            $section !== '' ? $section : null,
        );
        [$sectionStored, $teamStored] = array_slice(
            RegistryOrgAssignment::resolveForStorage(
                $registryDepartment,
                $location,
                $section !== '' ? $section : null,
                $team !== '' ? $team : null,
            ),
            0,
            2,
        );

        $validated['department'] = $affiliationOrg['department'] !== '' ? $affiliationOrg['department'] : null;
        $validated['section'] = RegistryOrgAssignment::combine($sectionStored, $teamStored);

        return $validated;
    }

    /**
     * @return array<string, string>
     */
    protected function affiliationOrgFieldMessages(): array
    {
        return [
            'company.in' => '所属会社を正しく選択してください。',
            'location.in' => '管轄を正しく選択してください。',
            'department.in' => '部を正しく選択してください。',
            'section.in' => '課/チームを正しく選択してください。',
        ];
    }
}
