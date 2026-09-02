<?php

namespace App\Support;

use App\Models\EmployeeHrDetail;

class HrDetailOrgFormState
{
    /**
     * @return array{department: string, location: string, section: string, team: string}
     */
    public static function assignmentValues(EmployeeHrDetail $detail, string $suffix): array
    {
        $departmentField = self::departmentField($suffix);
        $sectionField = self::sectionField($suffix);
        $teamField = self::teamField($suffix);

        $split = RegistryOrgAssignment::splitForRegistryForm(
            $detail->{$sectionField},
            $detail->{$departmentField},
            $detail->jurisdiction,
        );

        return [
            'department' => (string) old($departmentField, $detail->{$departmentField} ?? ''),
            'location' => (string) old('jurisdiction', $detail->jurisdiction ?? ''),
            'section' => (string) old($sectionField, $split['section'] ?? ''),
            'team' => (string) old($teamField, $split['team'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function mergeSectionFields(array $input): array
    {
        foreach (['primary', 'secondary'] as $suffix) {
            $departmentField = self::departmentField($suffix);
            $sectionField = self::sectionField($suffix);
            $teamField = self::teamField($suffix);

            if (! array_key_exists($departmentField, $input)
                && ! array_key_exists($sectionField, $input)
                && ! array_key_exists($teamField, $input)) {
                continue;
            }

            [, , $sectionPrimary] = RegistryOrgAssignment::resolveForStorage(
                (string) ($input[$departmentField] ?? ''),
                (string) ($input['jurisdiction'] ?? ''),
                (string) ($input[$sectionField] ?? ''),
                (string) ($input[$teamField] ?? ''),
            );

            $input[$sectionField] = $sectionPrimary !== '' && $sectionPrimary !== null ? $sectionPrimary : null;
            unset($input[$teamField]);
        }

        return $input;
    }

    public static function departmentField(string $suffix): string
    {
        return $suffix === 'primary' ? 'department_primary' : 'department_secondary';
    }

    public static function sectionField(string $suffix): string
    {
        return $suffix === 'primary' ? 'section_primary' : 'section_secondary';
    }

    public static function teamField(string $suffix): string
    {
        return $suffix === 'primary' ? 'team_primary' : 'team_secondary';
    }

    public static function departmentLabel(string $suffix): string
    {
        return $suffix === 'primary' ? '部署①' : '部署②';
    }

    public static function sectionLabel(string $suffix): string
    {
        return $suffix === 'primary' ? '課①' : '課②';
    }

    public static function teamLabel(string $suffix): string
    {
        return $suffix === 'primary' ? 'チーム①' : 'チーム②';
    }
}
