<?php

namespace App\Console\Commands;

use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\EmployeeRosterCsv;
use Illuminate\Console\Command;

class SyncJoinedAtFromRosterCommand extends Command
{
    protected $signature = 'employee:sync-joined-at
        {file=database/imports/employee-roster.csv : 社員名簿 CSV のパス}
        {--dry-run : 更新せず内容だけ表示}
        {--match-email-only : 氏名不一致でもメール一致なら更新（要確認）}';

    protected $description = '社員名簿 CSV の入社日（入社日→入社予定日）を、メール＋氏名が一致する社員に反映する';

    public function handle(): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));

        if (! is_readable($path)) {
            $this->error("CSVが見つかりません: {$path}");

            return self::FAILURE;
        }

        try {
            $rows = EmployeeRosterCsv::readRows($path);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->warn('入社日付きの行がありません。');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $matchEmailOnly = (bool) $this->option('match-email-only');
        $results = [];
        $errors = [];
        $unchanged = 0;
        $nameMismatches = 0;

        if ($matchEmailOnly && ! $dryRun) {
            $this->warn('--match-email-only: 氏名不一致でもメール一致で更新します。');
        }

        foreach ($rows as $row) {
            $user = User::query()
                ->with('profile')
                ->where('email', $row['email'])
                ->first();

            if (! $user) {
                $errors[] = "行 {$row['line']}: メール {$row['email']} に一致する社員が見つかりません（{$row['name']}）";

                continue;
            }

            if (! EmployeeRosterCsv::nameMatchesUser($row, $user)) {
                if (! $matchEmailOnly) {
                    $nameMismatches++;
                    $errors[] = "行 {$row['line']}: 氏名不一致 {$row['email']} CSV={$row['name']} DB={$user->displayName()}";

                    continue;
                }
            }

            $profile = $user->profile;
            $current = $profile?->joined_at?->toDateString();
            $next = $row['joined_at'];

            if ($current === $next) {
                $unchanged++;

                continue;
            }

            if ($dryRun) {
                $results[] = [
                    $row['email'],
                    $user->displayName(),
                    $current ?? '—',
                    $next,
                    '更新予定',
                ];

                continue;
            }

            EmployeeProfile::updateOrCreate(
                ['user_id' => $user->id],
                ['joined_at' => $next],
            );

            $results[] = [
                $row['email'],
                $user->displayName(),
                $current ?? '—',
                $next,
                '更新',
            ];
        }

        if ($results !== []) {
            $this->table(
                ['メール', '氏名', '現在の入社日', 'CSV入社日', '結果'],
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

        $updated = count(array_filter($results, fn (array $row) => in_array($row[4], ['更新', '更新予定'], true)));

        $stillImportDate = EmployeeProfile::query()
            ->whereDate('joined_at', '2026-06-24')
            ->count();

        $this->newLine();
        $this->info(sprintf(
            '%s: 更新 %d 件 / 変更なし %d 件 / スキップ %d 件',
            $dryRun ? 'dry-run' : '完了',
            $updated,
            $unchanged,
            count($errors),
        ));

        if ($nameMismatches > 0 && ! $matchEmailOnly) {
            $this->line("  うち氏名不一致: {$nameMismatches} 件（--match-email-only で反映可能）");
        }

        $this->line("  DB内 joined_at=2026-06-24: {$stillImportDate} 件");

        return self::SUCCESS;
    }

    private function resolvePath(string $file): string
    {
        if (str_starts_with($file, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $file)) {
            return $file;
        }

        return base_path($file);
    }
}
