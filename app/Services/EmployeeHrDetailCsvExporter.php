<?php

namespace App\Services;

use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\EmployeeHrDetailFieldLabels;
use App\Support\EmployeeHrDetailAccess;
use App\Support\RegistryGrAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EmployeeHrDetailCsvExporter
{
    /**
     * @param  Collection<int, User>  $users
     */
    public function stream(Collection $users, User $viewer): void
    {
        $this->streamUsers($users, $viewer);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function streamQuery(Builder $query, User $viewer): void
    {
        @set_time_limit(0);

        $columns = EmployeeHrDetailAccess::exportColumnNamesForBulk($viewer);
        $permissions = $this->bulkRowPermissions($viewer);

        $handle = fopen('php://output', 'w');
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, array_map(
            fn (string $column) => EmployeeHrDetailFieldLabels::label($column),
            $columns,
        ));

        foreach ($query->lazyById(100) as $user) {
            if (connection_aborted()) {
                break;
            }

            fputcsv(
                $handle,
                $this->row(
                    $viewer,
                    $user,
                    $user->hrDetail,
                    $columns,
                    $permissions[$user->id === $viewer->id ? 'self' : 'other'],
                ),
            );
        }

        fclose($handle);
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function streamUsers(Collection $users, User $viewer): void
    {
        $columns = EmployeeHrDetailAccess::exportColumnNames($viewer, $users);
        $permissions = $this->bulkRowPermissions($viewer);

        $handle = fopen('php://output', 'w');
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, array_map(
            fn (string $column) => EmployeeHrDetailFieldLabels::label($column),
            $columns,
        ));

        foreach ($users as $user) {
            fputcsv(
                $handle,
                $this->row(
                    $viewer,
                    $user,
                    $user->hrDetail,
                    $columns,
                    $permissions[$user->id === $viewer->id ? 'self' : 'other'],
                ),
            );
        }

        fclose($handle);
    }

    /**
     * @return array{
     *     self: array{meta: list<string>, fields: list<string>},
     *     other: array{meta: list<string>, fields: list<string>}
     * }
     */
    private function bulkRowPermissions(User $viewer): array
    {
        $other = new User(['id' => 0]);

        return [
            'self' => [
                'meta' => EmployeeHrDetailAccess::viewableMetaColumns($viewer, $viewer),
                'fields' => EmployeeHrDetailAccess::viewableFieldNames($viewer, $viewer),
            ],
            'other' => [
                'meta' => EmployeeHrDetailAccess::viewableMetaColumns($viewer, $other),
                'fields' => EmployeeHrDetailAccess::viewableFieldNames($viewer, $other),
            ],
        ];
    }

    public function filename(?User $user = null): string
    {
        $timestamp = now(config('app.timezone'))->format('Ymd_His');

        if ($user) {
            $id = $user->employee_id ?: (string) $user->id;

            return "hr_detail_{$id}_{$timestamp}.csv";
        }

        return "hr_details_{$timestamp}.csv";
    }

    /**
     * @param  list<string>  $columns
     * @param  array{meta: list<string>, fields: list<string>}  $permissions
     * @return list<mixed>
     */
    private function row(
        User $viewer,
        User $user,
        ?EmployeeHrDetail $detail,
        array $columns,
        array $permissions,
    ): array {
        $values = [];

        foreach ($columns as $column) {
            if (in_array($column, EmployeeHrDetailFieldLabels::META, true)) {
                $values[] = in_array($column, $permissions['meta'], true)
                    ? $this->metaValue($user, $detail, $column)
                    : '';

                continue;
            }

            $values[] = in_array($column, $permissions['fields'], true)
                ? $this->detailValue($user, $detail, $column)
                : '';
        }

        return $values;
    }

    private function metaValue(User $user, ?EmployeeHrDetail $detail, string $column): mixed
    {
        return match ($column) {
            'primary_id' => $detail?->primary_id ?? EmployeeHrDetail::generatePrimaryId($user),
            'employee_id' => $user->employee_id,
            'name' => $user->displayName(),
            'email' => $user->email,
            'english_name' => $user->profile?->english_name,
            'abbreviated_name' => $user->profile?->abbreviated_name,
            'nationality' => $user->profile?->nationality,
            'joined_at' => $this->formatDate($user->profile?->joined_at),
            default => '',
        };
    }

    private function detailValue(User $user, ?EmployeeHrDetail $detail, string $column): mixed
    {
        if ($column === 'department_primary') {
            return $this->exportDepartmentPrimary($user, $detail);
        }

        if (! $detail) {
            return '';
        }

        if ($column === 'affiliation_code') {
            return User::affiliationDisplayName($detail->affiliation_code) ?? '';
        }

        $value = $detail->{$column};

        if ($value instanceof Carbon) {
            return $this->formatDate($value);
        }

        if (is_bool($value)) {
            return $value ? 'はい' : 'いいえ';
        }

        return $value ?? '';
    }

    private function exportDepartmentPrimary(User $user, ?EmployeeHrDetail $detail): string
    {
        $jurisdiction = trim((string) ($detail?->jurisdiction ?? ''));
        $department = trim((string) ($detail?->department_primary ?? ''));

        if ($department === '' || $jurisdiction === '') {
            $affiliation = $user->relationLoaded('affiliationHistories')
                ? $user->currentAffiliation()
                : null;

            if ($department === '') {
                $department = trim((string) ($affiliation?->department ?? ''));
            }

            if ($jurisdiction === '') {
                $jurisdiction = trim((string) ($affiliation?->location ?? ''));
            }
        }

        return RegistryGrAssignment::rosterDepartmentLabel($jurisdiction, $department);
    }

    private function formatDate(?Carbon $value): string
    {
        return $value?->timezone(config('app.timezone'))->format('Y/m/d') ?? '';
    }
}
