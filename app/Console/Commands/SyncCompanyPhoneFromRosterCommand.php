<?php

namespace App\Console\Commands;

use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\CompanyPhone;
use App\Support\EmployeeRosterCsv;
use Illuminate\Console\Command;

class SyncCompanyPhoneFromRosterCommand extends Command
{
    protected $signature = 'employee:sync-company-phone-from-roster
        {file=database/imports/employee-roster.csv : 社員名簿 CSV のパス}
        {--dry-run : 更新せず内容だけ表示}
        {--match-email-only : 氏名不一致でもメール一致なら更新（要確認）}';

    protected $description = '社員名簿 CSV の電話番号を人事詳細の社用電話番号に反映する（他項目は変更しない）';

    public function handle(): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));

        if (! is_readable($path)) {
            $this->error("CSVが見つかりません: {$path}");

            return self::FAILURE;
        }

        try {
            $rows = EmployeeRosterCsv::readCompanyPhoneRows($path);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->warn('電話番号付きの行がありません。');

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
            $currentPhone = CompanyPhone::normalize($detail?->company_phone) ?? '';
            $csvPhone = CompanyPhone::normalize($row['company_phone']) ?? '';

            if ($currentPhone === $csvPhone) {
                $unchanged++;

                continue;
            }

            if ($dryRun) {
                $results[] = $this->resultRow($row, $user, $currentPhone, '更新予定');

                continue;
            }

            EmployeeHrDetail::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['company_phone' => $csvPhone],
            );

            $results[] = $this->resultRow($row, $user, $currentPhone, '更新');
        }

        if ($results !== []) {
            $this->table(
                ['メール', '氏名', '現在の社用電話', 'CSV電話番号', '結果'],
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

        $updated = count(array_filter($results, fn (array $result) => in_array($result[4], ['更新', '更新予定'], true)));

        $this->newLine();
        $this->info(sprintf(
            '%s: 更新 %d 件 / 変更なし %d 件 / スキップ %d 件',
            $dryRun ? 'dry-run' : '完了',
            $updated,
            $unchanged,
            count($errors),
        ));
        $this->line('  プロフィール・所属・状況・雇用形態・人事詳細の他項目は変更していません。');
        $this->line('  追加・更新するのは社用電話番号（company_phone）のみです。');

        return self::SUCCESS;
    }

    /**
     * @param  array{
     *     email: string,
     *     company_phone: string
     * }  $row
     * @return list<string>
     */
    private function resultRow(
        array $row,
        User $user,
        string $beforePhone,
        string $status,
    ): array {
        return [
            $row['email'],
            $user->displayName(),
            $beforePhone !== '' ? $beforePhone : '—',
            $row['company_phone'],
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
