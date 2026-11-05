<?php

namespace App\Support;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;

class HrDetailOrgFormState
{
    /**
     * @return array{department: string, location: string, section: string, team: string}
     */
    public static function assignmentValues(EmployeeHrDetail $detail, string $suffix, ?User $user = null): array
    {
        $departmentField = self::departmentField($suffix);
        $sectionField = self::sectionField($suffix);
        $teamField = self::teamField($suffix);

        if ($user !== null && ! old($departmentField) && ! old($sectionField) && ! old($teamField)) {
            $fromConcurrent = self::assignmentValuesFromConcurrentAffiliations($user, $suffix);
            if ($fromConcurrent !== null) {
                return $fromConcurrent;
            }
        }

        $split = RegistryOrgAssignment::splitForRegistryForm(
            $detail->{$sectionField},
            $detail->{$departmentField},
            $detail->jurisdiction,
        );

        return [
            'department' => RegistryDepartmentOptions::registryFormDepartment(
                (string) old($departmentField, $detail->{$departmentField} ?? ''),
                (string) old($sectionField, $split['section'] ?? ''),
            ),
            'location' => (string) old('jurisdiction', $detail->jurisdiction ?? ''),
            'section' => (string) old($sectionField, $split['section'] ?? ''),
            'team' => (string) old($teamField, $split['team'] ?? ''),
        ];
    }

    /**
     * 在籍中の兼任所属を部署①・②に割り当てる（詳細画面の表示用）。
     *
     * @return array{department: string, location: string, section: string, team: string}|null
     */
    private static function assignmentValuesFromConcurrentAffiliations(User $user, string $suffix): ?array
    {
        $rows = $user->concurrentOrgRows();
        if ($rows === null) {
            return null;
        }

        $row = self::concurrentRowForSuffix($rows, $suffix);
        if ($row === null) {
            return [
                'department' => '',
                'location' => '',
                'section' => '',
                'team' => '',
            ];
        }

        $split = RegistryOrgAssignment::splitForRegistryForm(
            $row['section'],
            $row['department'],
            $row['location'],
        );

        return [
            'department' => RegistryDepartmentOptions::registryFormDepartment(
                $row['department'],
                $split['section'] ?? '',
            ),
            'location' => $row['location'],
            'section' => (string) ($split['section'] ?? ''),
            'team' => (string) ($split['team'] ?? ''),
        ];
    }

    /**
     * @param  list<array{department: string, section: string, location: string}>  $rows
     * @return array{department: string, section: string, location: string}|null
     */
    private static function concurrentRowForSuffix(array $rows, string $suffix): ?array
    {
        if ($suffix === 'primary') {
            return $rows[0] ?? null;
        }

        $secondaryRows = array_slice($rows, 1);
        if ($secondaryRows === []) {
            return null;
        }

        return $secondaryRows[0];
    }

    /**
     * 部署③以降。在籍中の兼任が3件以上あるとき、3件目からを1行ずつ返す。
     *
     * @return list<array{affiliation_id: int|string, department: string, section: string, team: string, position: string, location: string}>
     */
    public static function extraAssignmentRows(?User $user): array
    {
        $oldRows = old('org_extra');
        if (is_array($oldRows)) {
            $rows = [];
            foreach ($oldRows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rows[] = [
                    'affiliation_id' => $row['affiliation_id'] ?? '',
                    'department' => trim((string) ($row['department'] ?? '')),
                    'section' => trim((string) ($row['section'] ?? '')),
                    'team' => trim((string) ($row['team'] ?? '')),
                    'position' => trim((string) ($row['position'] ?? '')),
                    'location' => '',
                ];
            }

            return $rows;
        }

        if ($user === null) {
            return [];
        }

        $affiliations = $user->concurrentAffiliations();
        if ($affiliations === null) {
            return [];
        }

        return $affiliations->slice(2)->map(function (AffiliationHistory $affiliation): array {
            $split = RegistryOrgAssignment::splitForRegistryForm(
                $affiliation->section,
                $affiliation->department,
                $affiliation->location,
            );

            return [
                'affiliation_id' => $affiliation->id,
                'department' => RegistryDepartmentOptions::registryFormDepartment(
                    (string) $affiliation->department,
                    (string) ($split['section'] ?? ''),
                ),
                'section' => (string) ($split['section'] ?? ''),
                'team' => (string) ($split['team'] ?? ''),
                'position' => trim((string) ($affiliation->position ?? '')),
                'location' => trim((string) ($affiliation->location ?? '')),
            ];
        })->values()->all();
    }

    public static function indexMark(int $number): string
    {
        $marks = ['①', '②', '③', '④', '⑤', '⑥', '⑦', '⑧', '⑨', '⑩'];

        return $marks[$number - 1] ?? (string) $number;
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

            [$sectionStored, $teamStored] = array_slice(
                RegistryOrgAssignment::resolveForStorage(
                    (string) ($input[$departmentField] ?? ''),
                    (string) ($input['jurisdiction'] ?? ''),
                    (string) ($input[$sectionField] ?? ''),
                    (string) ($input[$teamField] ?? ''),
                ),
                0,
                2,
            );

            $input[$departmentField] = RegistryDepartmentOptions::hrDetailDepartment(
                (string) ($input[$departmentField] ?? ''),
                (string) ($input[$sectionField] ?? ''),
            );
            $input[$sectionField] = RegistryOrgAssignment::combine($sectionStored, $teamStored)
                ?? ($sectionStored !== '' && $sectionStored !== null ? $sectionStored : null);
            unset($input[$teamField]);

            if ($suffix === 'primary') {
                [$normalizedDepartment, $normalizedJurisdiction, $normalizedSection] = RegistryOrgAssignment::normalizePrimaryOrgStorage(
                    (string) ($input[$departmentField] ?? ''),
                    (string) ($input['jurisdiction'] ?? ''),
                    (string) ($input[$sectionField] ?? ''),
                );

                if ($normalizedDepartment !== null) {
                    $input[$departmentField] = $normalizedDepartment;
                }

                if ($normalizedJurisdiction !== null) {
                    $input['jurisdiction'] = $normalizedJurisdiction;
                }

                if ($normalizedSection !== null) {
                    $input[$sectionField] = $normalizedSection;
                }
            }
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
