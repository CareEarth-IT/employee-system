<?php

namespace App\Console\Commands;

use App\Models\EmployeeHrDetail;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\EmployeeRegistryService;
use App\Support\EmployeeIdRules;
use App\Support\EmployeeRosterCsv;
use Illuminate\Console\Command;

class SyncRegistryIdentityFromRosterCommand extends Command
{
    protected $signature = 'employee:sync-registry-identity-from-roster
        {file=database/imports/employee-roster.csv : 社員名簿 CSV のパス}
        {--dry-run : 更新せず内容だけ表示}
        {--match-email-only : 氏名不一致でもメール一致なら更新（要確認）}';

    protected $description = '社員名簿 CSV の氏名・ID・プロフィール・性別・国籍・備考・管轄を反映する';

    public function handle(EmployeeRegistryService $registryService): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));

        if (! is_readable($path)) {
            $this->error("CSVが見つかりません: {$path}");

            return self::FAILURE;
        }

        try {
            $rows = EmployeeRosterCsv::readRegistryIdentityRows($path);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->warn('氏名・ID・プロフィール等の更新対象行がありません。');

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
                ->with(['profile', 'hrDetail'])
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

            $updates = $this->buildUpdates($row, $user, $registryService, $errors);

            if ($updates === []) {
                $unchanged++;

                continue;
            }

            if ($dryRun) {
                $results[] = [$row['email'], $user->displayName(), implode(', ', array_keys($updates)), '更新予定'];

                continue;
            }

            $this->applyUpdates($user, $updates);
            $results[] = [$row['email'], $user->fresh(['profile', 'hrDetail'])->displayName(), implode(', ', array_keys($updates)), '更新'];
        }

        if ($results !== []) {
            $this->table(['メール', '氏名', '更新項目', '結果'], $results);
        }

        if ($errors !== []) {
            $this->newLine();
            $this->warn('スキップ / 不一致: '.count($errors).' 件');
            foreach ($errors as $error) {
                $this->line($error);
            }
        }

        $updated = count(array_filter($results, fn (array $result) => in_array($result[3], ['更新', '更新予定'], true)));

        $this->newLine();
        $this->info(sprintf(
            '%s: 更新 %d 件 / 変更なし %d 件 / スキップ %d 件',
            $dryRun ? 'dry-run' : '完了',
            $updated,
            $unchanged,
            count($errors),
        ));
        $this->line('  入社日・所属・状況・雇用形態・電話番号など他項目は変更していません。');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function buildUpdates(
        array $row,
        User $user,
        EmployeeRegistryService $registryService,
        array &$errors,
    ): array {
        $updates = [];
        $profile = $user->profile;
        $detail = $user->hrDetail;

        if ($row['name'] !== '') {
            [$lastName, $firstName, $displayName] = $registryService->splitName($row['name']);

            if ($user->name !== $displayName || $user->last_name !== $lastName || $user->first_name !== $firstName) {
                $updates['user'] = [
                    'last_name' => $lastName,
                    'first_name' => $firstName,
                    'name' => $displayName,
                ];
            }
        }

        if ($row['employee_id'] !== '') {
            if (! EmployeeIdRules::isValid($row['employee_id'])) {
                $errors[] = "行 {$row['line']}: 社員IDが不正 {$row['email']} ID={$row['employee_id']}";
            } elseif (
                $user->employee_id !== $row['employee_id']
                && User::query()
                    ->where('employee_id', $row['employee_id'])
                    ->whereKeyNot($user->id)
                    ->exists()
            ) {
                $errors[] = "行 {$row['line']}: 社員ID重複 {$row['email']} ID={$row['employee_id']}";
            } elseif ($user->employee_id !== $row['employee_id']) {
                $updates['user'] = ($updates['user'] ?? []) + ['employee_id' => $row['employee_id']];
            }
        }

        $profileUpdates = [];

        foreach (['name_kana', 'english_name', 'abbreviated_name', 'nationality'] as $field) {
            if ($row[$field] === '') {
                continue;
            }

            $current = trim((string) ($profile?->{$field} ?? ''));

            if ($current !== $row[$field]) {
                $profileUpdates[$field] = $row[$field];
            }
        }

        if ($profileUpdates !== []) {
            $updates['profile'] = $profileUpdates;
        }

        $detailUpdates = [];

        foreach (['gender', 'remarks', 'jurisdiction'] as $field) {
            if ($row[$field] === '') {
                continue;
            }

            $current = trim((string) ($detail?->{$field} ?? ''));

            if ($current !== $row[$field]) {
                $detailUpdates[$field] = $row[$field];
            }
        }

        if ($row['birth_date'] !== null && $row['birth_date'] !== '') {
            $currentBirthDate = $detail?->birth_date?->toDateString() ?? '';

            if ($currentBirthDate !== $row['birth_date']) {
                $detailUpdates['birth_date'] = $row['birth_date'];
            }
        }

        if ($row['name_kana'] !== '') {
            $currentKana = trim((string) ($detail?->name_kana_fullwidth ?? ''));

            if ($currentKana !== $row['name_kana']) {
                $detailUpdates['name_kana_fullwidth'] = $row['name_kana'];
            }
        }

        if ($detailUpdates !== []) {
            $updates['hr_detail'] = $detailUpdates;
        }

        return $updates;
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    private function applyUpdates(User $user, array $updates): void
    {
        if (isset($updates['user'])) {
            $user->fill($updates['user']);
            $user->save();
        }

        if (isset($updates['profile'])) {
            EmployeeProfile::updateOrCreate(
                ['user_id' => $user->id],
                $updates['profile'],
            );
        }

        if (isset($updates['hr_detail'])) {
            EmployeeHrDetail::query()->updateOrCreate(
                ['user_id' => $user->id],
                $updates['hr_detail'],
            );
        }
    }

    private function resolvePath(string $file): string
    {
        if (str_starts_with($file, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $file)) {
            return $file;
        }

        return base_path($file);
    }
}
