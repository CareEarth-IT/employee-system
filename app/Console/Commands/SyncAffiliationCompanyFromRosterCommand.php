<?php

namespace App\Console\Commands;

use App\Models\AffiliationHistory;
use App\Models\User;
use App\Support\EmployeeRosterCsv;
use Illuminate\Console\Command;

class SyncAffiliationCompanyFromRosterCommand extends Command
{
    /** 一括インポート時に誤って設定された開始日 */
    public const BULK_IMPORT_START_DATE = '2026-06-24';

    /** 手動で部署を修正した所属の開始日（この行は変更しない） */
    public const MANUAL_CORRECTION_START_DATE = '2026-07-01';

    protected $signature = 'employee:sync-affiliation-company
        {file=database/imports/employee-roster.csv : 社員名簿 CSV のパス}
        {--dry-run : 更新せず内容だけ表示}
        {--match-email-only : 氏名不一致でもメール一致なら更新（要確認）}';

    protected $description = '社員名簿 CSV の所属コード（CE/GT/EM/MD/ME）を所属会社名に反映する（部署・開始日など他項目は変更しない）';

    public function handle(): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));

        if (! is_readable($path)) {
            $this->error("CSVが見つかりません: {$path}");

            return self::FAILURE;
        }

        try {
            $rows = EmployeeRosterCsv::readAffiliationRows($path);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->warn('所属コード付きの行がありません。');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $matchEmailOnly = (bool) $this->option('match-email-only');
        $results = [];
        $errors = [];
        $unchanged = 0;
        $skippedManual = 0;

        if ($matchEmailOnly && ! $dryRun) {
            $this->warn('--match-email-only: 氏名不一致でもメール一致で更新します。');
        }

        foreach ($rows as $row) {
            $user = User::query()
                ->with('affiliationHistories')
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

            $affiliationCount = $user->affiliationHistories->count();
            $targetCompany = $row['company'];

            foreach ($user->affiliationHistories as $affiliation) {
                if ($affiliation->start_date->toDateString() === self::MANUAL_CORRECTION_START_DATE) {
                    $skippedManual++;
                    $unchanged++;

                    continue;
                }

                if (! $this->shouldUpdateCompany($affiliation, $affiliationCount)) {
                    $unchanged++;

                    continue;
                }

                $currentCompany = $affiliation->company ?? '—';

                if ($currentCompany === $targetCompany) {
                    $unchanged++;

                    continue;
                }

                if ($dryRun) {
                    $results[] = [
                        $row['email'],
                        $user->displayName(),
                        $row['affiliation_code'],
                        $currentCompany,
                        $targetCompany,
                        $affiliation->start_date->toDateString(),
                        $affiliation->department ?? '—',
                        '更新予定',
                    ];

                    continue;
                }

                $affiliation->update(['company' => $targetCompany]);

                $results[] = [
                    $row['email'],
                    $user->displayName(),
                    $row['affiliation_code'],
                    $currentCompany,
                    $targetCompany,
                    $affiliation->start_date->toDateString(),
                    $affiliation->department ?? '—',
                    '更新',
                ];
            }
        }

        if ($results !== []) {
            $this->table(
                ['メール', '氏名', 'CSV', '現在の会社', 'CSV会社', '開始日', '部', '結果'],
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

        $updated = count(array_filter($results, fn (array $row) => in_array($row[7], ['更新', '更新予定'], true)));

        $this->newLine();
        $this->info(sprintf(
            '%s: 更新 %d 件 / 変更なし %d 件 / スキップ %d 件',
            $dryRun ? 'dry-run' : '完了',
            $updated,
            $unchanged,
            count($errors),
        ));
        $this->line('  部署・開始日・役職など他項目は変更していません。');
        $this->line('  start_date='.self::MANUAL_CORRECTION_START_DATE.' の所属はスキップ: '.$skippedManual.' 件');

        return self::SUCCESS;
    }

    private function shouldUpdateCompany(
        AffiliationHistory $affiliation,
        int $affiliationCount,
    ): bool {
        $start = $affiliation->start_date->toDateString();

        if ($start === self::BULK_IMPORT_START_DATE) {
            return true;
        }

        return $affiliationCount === 1;
    }

    private function resolvePath(string $file): string
    {
        if (str_starts_with($file, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $file)) {
            return $file;
        }

        return base_path($file);
    }
}
