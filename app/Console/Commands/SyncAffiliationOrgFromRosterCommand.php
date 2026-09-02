<?php

namespace App\Console\Commands;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\EmployeeRosterCsv;
use App\Support\RegistryDepartmentOptions;
use Illuminate\Console\Command;

class SyncAffiliationOrgFromRosterCommand extends Command
{
    protected $signature = 'employee:sync-affiliation-org-from-roster
        {file=database/imports/employee-roster.csv : 社員名簿 CSV のパス}
        {--dry-run : 更新せず内容だけ表示}
        {--match-email-only : 氏名不一致でもメール一致なら更新（要確認）}';

    protected $description = '社員名簿 CSV の管轄・部署・課/チーム・役職を在籍中の所属に反映する';

    public function handle(): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));

        if (! is_readable($path)) {
            $this->error("CSVが見つかりません: {$path}");

            return self::FAILURE;
        }

        try {
            $rows = EmployeeRosterCsv::readAffiliationOrgRows($path);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->warn('所属組織の更新対象行がありません。');

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
                ->with(['profile', 'affiliationHistories'])
                ->where('email', $row['email'])
                ->first();

            if (! $user) {
                $errors[] = "行 {$row['line']}: メール {$row['email']} に一致する社員が見つかりません（{$row['name']}）";

                continue;
            }

            if (! EmployeeRosterCsv::nameMatchesUser($row, $user) && ! $matchEmailOnly) {
                $errors[] = "行 {$row['line']}: 氏名不一致 {$row['email']} CSV={$row['name']} DB={$user->displayName()}";

                continue;
            }

            $updates = $this->buildUpdates($row, $user, $errors);

            if ($updates === []) {
                $unchanged++;

                continue;
            }

            $affiliation = $user->currentAffiliation();

            if ($affiliation === null) {
                $startDate = $user->profile?->joined_at?->toDateString() ?? now()->toDateString();

                if ($dryRun) {
                    $results[] = $this->resultRow($row, $user, null, $updates, '新規所属予定');

                    continue;
                }

                $affiliation = AffiliationHistory::create([
                    'user_id' => $user->id,
                    'start_date' => $startDate,
                    'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                    'company' => User::COMPANY_NAMES[0] ?? 'CareEarth',
                    'location' => $updates['location'] ?? null,
                    'department' => $updates['department'] ?? '',
                    'section' => $updates['section'] ?? null,
                    'position' => $updates['position'] ?? '',
                ]);
                $user->closeOtherEnrolledAffiliations($affiliation);
                $user->syncRoleFromAffiliation();
                EmployeeHrDetail::syncPrimaryOrgFromAffiliation($user->fresh(), $affiliation);
                $results[] = $this->resultRow($row, $user, $affiliation, $updates, '新規所属');

                continue;
            }

            if ($dryRun) {
                $results[] = $this->resultRow($row, $user, $affiliation, $updates, '更新予定');

                continue;
            }

            $affiliation->update($updates);
            $user->syncRoleFromAffiliation();
            EmployeeHrDetail::syncPrimaryOrgFromAffiliation($user->fresh(), $affiliation->fresh());
            $results[] = $this->resultRow($row, $user, $affiliation->fresh(), $updates, '更新');
        }

        if ($results !== []) {
            $this->table(
                ['メール', '氏名', '管轄', '部署', '課/チーム', '役職', '結果'],
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

        $updated = count(array_filter(
            $results,
            fn (array $result) => in_array($result[6], ['更新', '更新予定', '新規所属', '新規所属予定'], true),
        ));

        $this->newLine();
        $this->info(sprintf(
            '%s: 更新 %d 件 / 変更なし %d 件 / スキップ %d 件',
            $dryRun ? 'dry-run' : '完了',
            $updated,
            $unchanged,
            count($errors),
        ));
        $this->line('  所属会社名・開始日・人事詳細の他項目は変更していません。');
        $this->line('  在籍中の所属を更新した場合は、詳細情報の部署①・課/チーム①も同期します。');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $errors
     * @return array<string, string|null>
     */
    private function buildUpdates(array $row, User $user, array &$errors): array
    {
        $updates = [];
        $affiliation = $user->currentAffiliation();

        if ($row['department'] !== '') {
            $resolved = RegistryDepartmentOptions::resolveAffiliation($row['department']);
            $department = $resolved['department'];
            $current = trim((string) ($affiliation?->department ?? ''));

            if ($current !== $department) {
                $updates['department'] = $department;
            }

            if ($row['section'] === '' && $resolved['section'] !== null) {
                $currentSection = trim((string) ($affiliation?->section ?? ''));

                if ($currentSection !== $resolved['section']) {
                    $updates['section'] = $resolved['section'];
                }
            }
        }

        if ($row['section'] !== '') {
            $currentSection = trim((string) ($affiliation?->section ?? ''));

            if ($currentSection !== $row['section']) {
                $updates['section'] = $row['section'];
            }
        }

        if ($row['location'] !== '') {
            if (! in_array($row['location'], User::OFFICE_LOCATIONS, true)) {
                $errors[] = "行 {$row['line']}: 管轄が不正 {$row['email']} 管轄={$row['location']}";
            } else {
                $currentLocation = trim((string) ($affiliation?->location ?? ''));

                if ($currentLocation !== $row['location']) {
                    $updates['location'] = $row['location'];
                }
            }
        }

        if ($row['position'] !== '') {
            $currentPosition = trim((string) ($affiliation?->position ?? ''));

            if ($currentPosition !== $row['position']) {
                $updates['position'] = $row['position'];
            }
        }

        return $updates;
    }

    /**
     * @param  array<string, string|null>  $updates
     * @return list<string>
     */
    private function resultRow(
        array $row,
        User $user,
        ?AffiliationHistory $affiliation,
        array $updates,
        string $status,
    ): array {
        return [
            $row['email'],
            $user->displayName(),
            $this->displayChange($affiliation?->location, $updates['location'] ?? $row['location']),
            $this->displayChange($affiliation?->department, $updates['department'] ?? $row['department']),
            $this->displayChange($affiliation?->section, $updates['section'] ?? $row['section']),
            $this->displayChange($affiliation?->position, $updates['position'] ?? $row['position']),
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
