<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const DEPARTMENT_RENAMES = [
        '人材派遣事業部' => '営業部',
        'GR部（グローバル部）' => 'グローバル事業部',
        'GR部(グローバル部)' => 'グローバル事業部',
    ];

    public function up(): void
    {
        foreach (self::DEPARTMENT_RENAMES as $from => $to) {
            $this->renameDepartmentColumn('affiliation_histories', 'department', $from, $to);
            $this->renameDepartmentColumn('employee_hr_details', 'department_primary', $from, $to);
            $this->renameDepartmentColumn('employee_hr_details', 'department_secondary', $from, $to);
            $this->renameDepartmentColumn('scheduled_affiliation_changes', 'department', $from, $to);
        }
    }

    public function down(): void
    {
        foreach (array_flip(self::DEPARTMENT_RENAMES) as $from => $to) {
            // 部分置換は「大阪グローバル事業部」等を壊すため、完全一致のみ戻す
            foreach ([
                ['affiliation_histories', 'department'],
                ['employee_hr_details', 'department_primary'],
                ['employee_hr_details', 'department_secondary'],
                ['scheduled_affiliation_changes', 'department'],
            ] as [$table, $column]) {
                if (! $this->tableHasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)->where($column, $from)->update([$column => $to]);
            }
        }
    }

    private function renameDepartmentColumn(string $table, string $column, string $from, string $to): void
    {
        if (! $this->tableHasColumn($table, $column)) {
            return;
        }

        DB::table($table)->where($column, $from)->update([$column => $to]);

        DB::table($table)
            ->where($column, 'like', '%'.$from.'%')
            ->orderBy('id')
            ->each(function ($row) use ($table, $column, $from, $to): void {
                $current = (string) ($row->{$column} ?? '');
                $updated = str_replace($from, $to, $current);

                if ($updated !== $current) {
                    DB::table($table)->where('id', $row->id)->update([$column => $updated]);
                }
            });
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        return DB::getSchemaBuilder()->hasTable($table)
            && DB::getSchemaBuilder()->hasColumn($table, $column);
    }
};
