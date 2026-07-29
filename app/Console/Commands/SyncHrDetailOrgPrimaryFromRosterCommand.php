<?php

namespace App\Console\Commands;

use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\EmployeeRosterCsv;
use Illuminate\Console\Command;

class SyncHrDetailOrgPrimaryFromRosterCommand extends Command
{
    protected $signature = 'employee:sync-hr-detail-org-primary-from-roster
        {file=database/imports/employee-roster.csv : 社員名簿 CSV のパス}
        {--dry-run : 更新せず内容だけ表示}
        {--match-email-only : 氏名不一致でもメール一致なら更新（要確認）}';

    protected $description = '社員名簿 CSV の所属・部署①・課/チーム①・役職①を人事詳細に反映する（他項目は変更しない）';

    public function handle(): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));

        if (! is_readable($path)) {
            $this->error("CSVが見つかりません: {$path}");

            return self::FAILURE;
        }

        try {
            $rows = EmployeeRosterCsv::readHrDetailOrgPrimaryRows($path);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->warn('所属・部署・課・役職付きの行がありません。');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $matchEmailOnly = (bool) $this->option('match-email-only');
        $results = [];
        $errors = [];
        $unchanged = 0;

        if ($matchEmailOnly && ! $dryRun) {
            $this->warn('--match-email-only: 氏名不一致でもメール一致で更新します。');
        }

        foreach ($rows as $row) {
            $user = User::query()
                ->with('hrDetail')
                ->where('email', $row['email'])
                ->first();

            if (! $user) {
                $errors[] = "行 {$row['line']}: メール {$row['email']} に一致する社員が見つかりません（{$row['name']}）";

                continue;
            }

            if (! EmployeeRosterCsv::nameMatchesUser($row, $user)) {
                if (! $matchEmailOnly) {
                    $errors[] = "行 {$row['line']}: 氏名不一致 {$row['email']} CSV={$row['name']} DB={$user->displayName()}";

                    continue;
                }
            }

            $detail = $user->hrDetail;
            $updates = $this->buildUpdates($row, $detail);

            if ($updates === []) {
                $unchanged++;

                continue;
            }

            if ($dryRun) {
                $results[] = $this->resultRow($row, $user, $detail, '更新予定');

                continue;
            }

            EmployeeHrDetail::query()->updateOrCreate(
                ['user_id' => $user->id],
                $updates,
            );

            $results[] = $this->resultRow($row, $user, $detail, '更新');
        }

        if ($results !== []) {
            $this->table(
                ['メール', '氏名', '所属', '部署①', '課①', '役職①', '結果'],
                $results,
            );
        }

        if ($errors !== []) {
            $this->newLine();
            $this->warn('スキップ / 不一致: '.count($errors).' 件');
            foreach ($errors as $error) {
                $this->line($error);
            }
        }

        $updated = count(array_filter($results, fn (array $result) => in_array($result[6], ['更新', '更新予定'], true)));

        $this->newLine();
        $this->info(sprintf(
            '%s: 更新 %d 件 / 変更なし %d 件 / スキップ %d 件',
            $dryRun ? 'dry-run' : '完了',
            $updated,
            $unchanged,
            count($errors),
        ));
        $this->line('  状況・雇用形態・電話番号・備品購入・プロフィールなど他項目は変更していません。');
        $this->line('  追加・更新するのは所属・部署①・課/チーム①・役職①のみです。');
        $this->line('  CSV が空の項目は DB の値をそのまま残します。');

        return self::SUCCESS;
    }

    /**
     * @param  array{
     *     affiliation_code: string,
     *     department_primary: string,
     *     section_primary: string,
     *     position_primary: string
     * }  $row
     * @return array<string, string>
     */
    private function buildUpdates(array $row, ?EmployeeHrDetail $detail): array
    {
        $updates = [];

        foreach ([
            'affiliation_code',
            'department_primary',
            'section_primary',
            'position_primary',
        ] as $field) {
            $csvValue = $row[$field];

            if ($csvValue === '') {
                continue;
            }

            $current = trim((string) ($detail?->{$field} ?? ''));

            if ($current !== $csvValue) {
                $updates[$field] = $csvValue;
            }
        }

        return $updates;
    }

    /**
     * @param  array{
     *     email: string,
     *     affiliation_code: string,
     *     department_primary: string,
     *     section_primary: string,
     *     position_primary: string
     * }  $row
     * @return list<string>
     */
    private function resultRow(
        array $row,
        User $user,
        ?EmployeeHrDetail $detail,
        string $status,
    ): array {
        return [
            $row['email'],
            $user->displayName(),
            $this->displayChange($detail?->affiliation_code, $row['affiliation_code']),
            $this->displayChange($detail?->department_primary, $row['department_primary']),
            $this->displayChange($detail?->section_primary, $row['section_primary']),
            $this->displayChange($detail?->position_primary, $row['position_primary']),
            $status,
        ];
    }

    private function displayChange(?string $before, string $csv): string
    {
        if ($csv === '') {
            return '—';
        }

        $before = trim((string) $before);

        if ($before === '' || $before === $csv) {
            return $csv;
        }

        return "{$before} → {$csv}";
    }

    private function resolvePath(string $file): string
    {
        if (str_starts_with($file, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $file)) {
            return $file;
        }

        return base_path($file);
    }
}
