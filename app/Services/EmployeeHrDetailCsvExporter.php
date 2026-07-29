<?php

namespace App\Services;

use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\EmployeeHrDetailFieldLabels;
use App\Support\EmployeeHrDetailAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EmployeeHrDetailCsvExporter
{
    /**
     * @param  Collection<int, User>  $users
     */
    public function stream(Collection $users, User $viewer): void
    {
        $columns = EmployeeHrDetailAccess::exportColumnNames($viewer, $users);

        $handle = fopen('php://output', 'w');
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, array_map(
            fn (string $column) => EmployeeHrDetailFieldLabels::label($column),
            $columns,
        ));

        foreach ($users as $user) {
            $detail = $user->hrDetail;
            fputcsv($handle, $this->row($viewer, $user, $detail, $columns));
        }

        fclose($handle);
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
     * @return list<mixed>
     */
    private function row(User $viewer, User $user, ?EmployeeHrDetail $detail, array $columns): array
    {
        $viewableMeta = EmployeeHrDetailAccess::viewableMetaColumns($viewer, $user);
        $viewableFields = EmployeeHrDetailAccess::viewableFieldNames($viewer, $user);
        $values = [];

        foreach ($columns as $column) {
            if (in_array($column, EmployeeHrDetailFieldLabels::META, true)) {
                $values[] = in_array($column, $viewableMeta, true)
                    ? $this->metaValue($user, $detail, $column)
                    : '';

                continue;
            }

            $values[] = in_array($column, $viewableFields, true)
                ? $this->detailValue($detail, $column)
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
            'joined_at' => $this->formatDate($user->profile?->joined_at),
            default => '',
        };
    }

    private function detailValue(?EmployeeHrDetail $detail, string $column): mixed
    {
        if (! $detail) {
            return '';
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

    private function formatDate(?Carbon $value): string
    {
        return $value?->timezone(config('app.timezone'))->format('Y/m/d') ?? '';
    }
}
