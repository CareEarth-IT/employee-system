<?php

namespace App\Console\Commands;

use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\EmployeeRosterCsv;
use Illuminate\Console\Command;

class SyncHrDetailFromRosterCommand extends Command
{
    protected $signature = 'employee:sync-hr-detail-from-roster
        {file=database/imports/employee-roster.csv : 社員名簿 CSV のパス}
        {--dry-run : 更新せず内容だけ表示}
        {--match-email-only : 氏名不一致でもメール一致なら更新（要確認）}';

    protected $description = '社員名簿 CSV の状況・雇用形態を人事詳細に反映する（所属・備品購入・その他は変更しない）';

    public function handle(): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));

        if (! is_readable($path)) {
            $this->error("CSVが見つかりません: {$path}");

            return self::FAILURE;
        }

        try {
            $rows = EmployeeRosterCsv::readHrDetailRows($path);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->warn('状況・雇用形態付きの行がありません。');

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

            $beforeStatus = trim((string) ($detail?->employment_status ?? ''));
            $beforeType = trim((string) ($detail?->employment_type ?? ''));

            if ($dryRun) {
                $results[] = $this->resultRow(
                    $row,
                    $user,
                    $beforeStatus,
                    $beforeType,
                    '更新予定',
                );

                continue;
            }

            if (! $detail) {
                $detail = EmployeeHrDetail::query()->create([
                    'user_id' => $user->id,
                    ...$updates,
                ]);
            } else {
                $detail->update($updates);
            }

            $results[] = $this->resultRow(
                $row,
                $user,
                $beforeStatus,
                $beforeType,
                '更新',
            );
        }

        if ($results !== []) {
            $this->table(
                ['メール', '氏名', '現在状況', 'CSV状況', '現在雇用形態', 'CSV雇用形態', '結果'],
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
        $this->line('  所属・プロフィール・備品購入・人事詳細の他項目は変更していません。');
        $this->line('  追加・更新するのは状況と雇用形態のみです。');
        $this->line('  CSV が空の項目は DB の値をそのまま残します。');

        return self::SUCCESS;
    }

    /**
     * @param  array{
     *     employment_status: string,
     *     employment_type: string
     * }  $row
     * @return array<string, string>
     */
    private function buildUpdates(array $row, ?EmployeeHrDetail $detail): array
    {
        $updates = [];

        if ($row['employment_status'] !== '') {
            $currentStatus = trim((string) ($detail?->employment_status ?? ''));

            if ($currentStatus !== $row['employment_status']) {
                $updates['employment_status'] = $row['employment_status'];
            }
        }

        if ($row['employment_type'] !== '') {
            $currentType = trim((string) ($detail?->employment_type ?? ''));

            if ($currentType !== $row['employment_type']) {
                $updates['employment_type'] = $row['employment_type'];
            }
        }

        return $updates;
    }

    /**
     * @param  array{
     *     email: string,
     *     name: string,
     *     employment_status: string,
     *     employment_type: string
     * }  $row
     * @param  array<string, string>  $updates
     * @return list<string>
     */
    private function resultRow(
        array $row,
        User $user,
        string $beforeStatus,
        string $beforeType,
        string $status,
    ): array {
        return [
            $row['email'],
            $user->displayName(),
            $beforeStatus !== '' ? $beforeStatus : '—',
            $row['employment_status'] !== '' ? $row['employment_status'] : '—',
            $beforeType !== '' ? $beforeType : '—',
            $row['employment_type'] !== '' ? $row['employment_type'] : '—',
            $status,
        ];
    }

    private function resolvePath(string $file): string
    {
        if (str_starts_with($file, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $file)) {
            return $file;
        }

        return base_path($file);
    }
}
