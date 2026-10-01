<?php

namespace App\Console\Commands;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreResignedFromBackupCommand extends Command
{
    protected $signature = 'employee:restore-resigned-from-backup
        {users=deploy/roster-job-data/resigned-users.csv : バックアップ時点の退職者 CSV}
        {affiliations=deploy/roster-job-data/resigned-affiliations.csv : バックアップ時点の所属 CSV}
        {--dry-run : 更新せず差分だけ表示}';

    protected $description = '同期前バックアップ由来の退職者 CSV から、既存退職者の上書き分を戻す（新規登録分は対象外）';

    /** @var list<string> */
    private const USER_HEADERS = [
        'id', 'email', 'employee_id', 'name', 'last_name', 'first_name',
        'name_kana', 'english_name', 'abbreviated_name', 'nationality', 'joined_at',
        'employment_status', 'employment_type', 'resigned_at', 'last_working_day',
        'gender', 'birth_date', 'remarks', 'jurisdiction', 'name_kana_fullwidth',
        'department_primary', 'section_primary', 'position_primary',
        'department_secondary', 'section_secondary', 'position_secondary',
        'company_phone', 'affiliation_code',
    ];

    /** @var list<string> */
    private const AFFILIATION_HEADERS = [
        'id', 'user_id', 'email', 'company', 'department', 'section', 'position',
        'location', 'start_date', 'end_date', 'enrollment_status', 'job_description', 'import_locked',
    ];

    public function handle(): int
    {
        $usersPath = $this->resolvePath((string) $this->argument('users'));
        $affiliationsPath = $this->resolvePath((string) $this->argument('affiliations'));
        $dryRun = (bool) $this->option('dry-run');

        if (! is_readable($usersPath)) {
            $this->error("ユーザーCSVが見つかりません: {$usersPath}");

            return self::FAILURE;
        }

        if (! is_readable($affiliationsPath)) {
            $this->error("所属CSVが見つかりません: {$affiliationsPath}");

            return self::FAILURE;
        }

        $userRows = $this->readCsv($usersPath, self::USER_HEADERS);
        $affiliationRows = $this->readCsv($affiliationsPath, self::AFFILIATION_HEADERS);

        $this->info($dryRun ? 'dry-run: 既存退職者をバックアップ値へ戻す想定を表示します。' : '既存退職者をバックアップ値へ戻します。');
        $this->line('対象はバックアップ時点で退職だった社員のみ（CSV '.count($userRows).' 名）。今回の新規登録は対象外です。');

        $results = [];
        $skipped = [];
        $unchanged = 0;
        $updatedUsers = 0;
        $updatedAffiliations = 0;

        DB::transaction(function () use (
            $userRows,
            $affiliationRows,
            $dryRun,
            &$results,
            &$skipped,
            &$unchanged,
            &$updatedUsers,
            &$updatedAffiliations,
        ) {
            foreach ($userRows as $row) {
                $email = strtolower(trim($row['email']));
                $user = User::query()->with(['profile', 'hrDetail'])->whereRaw('LOWER(email) = ?', [$email])->first();

                if (! $user) {
                    $skipped[] = "{$row['email']}: 本番に存在しない（スキップ）";

                    continue;
                }

                $userChanges = $this->userChanges($user, $row);
                $profileChanges = $this->profileChanges($user->profile, $row);
                $detailChanges = $this->detailChanges($user->hrDetail, $row);

                if ($userChanges === [] && $profileChanges === [] && $detailChanges === []) {
                    $unchanged++;
                } else {
                    $fieldList = array_values(array_filter([
                        $userChanges !== [] ? 'user:'.implode('|', array_keys($userChanges)) : null,
                        $profileChanges !== [] ? 'profile:'.implode('|', array_keys($profileChanges)) : null,
                        $detailChanges !== [] ? 'hr_detail:'.implode('|', array_keys($detailChanges)) : null,
                    ]));

                    if (isset($userChanges['employee_id'])) {
                        $conflict = User::query()
                            ->where('employee_id', $userChanges['employee_id'])
                            ->whereKeyNot($user->id)
                            ->exists();

                        if ($conflict) {
                            $skipped[] = "{$row['email']}: 社員ID {$userChanges['employee_id']} が他ユーザーと重複のためスキップ";

                            continue;
                        }
                    }

                    $results[] = [
                        $row['email'],
                        $row['employee_id'],
                        (string) $user->employee_id,
                        implode(', ', $fieldList),
                        $dryRun ? '復元予定' : '復元',
                    ];

                    if (! $dryRun) {
                        if ($userChanges !== []) {
                            $user->update($userChanges);
                        }

                        if ($profileChanges !== []) {
                            if ($user->profile) {
                                $user->profile->update($profileChanges);
                            } else {
                                EmployeeProfile::query()->create(['user_id' => $user->id] + $profileChanges);
                            }
                        }

                        if ($detailChanges !== []) {
                            if ($user->hrDetail) {
                                $user->hrDetail->update($detailChanges);
                            } else {
                                EmployeeHrDetail::query()->create(['user_id' => $user->id] + $detailChanges);
                            }
                        }
                    }

                    $updatedUsers++;
                }
            }

            $affiliationsByEmail = [];
            foreach ($affiliationRows as $row) {
                $affiliationsByEmail[strtolower(trim($row['email']))][] = $row;
            }

            foreach ($affiliationsByEmail as $email => $rows) {
                $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

                if (! $user) {
                    continue;
                }

                foreach ($rows as $row) {
                    $affiliation = AffiliationHistory::query()->find((int) $row['id']);

                    if ($affiliation && (int) $affiliation->user_id !== (int) $user->id) {
                        $skipped[] = "{$email}: 所属ID {$row['id']} が別ユーザーに紐づくためスキップ";

                        continue;
                    }

                    $payload = [
                        'user_id' => $user->id,
                        'company' => $this->nullIfEmpty($row['company']),
                        'department' => $this->nullIfEmpty($row['department']),
                        'section' => $this->nullIfEmpty($row['section']),
                        'position' => $this->nullIfEmpty($row['position']),
                        'location' => $this->nullIfEmpty($row['location']),
                        'start_date' => $this->nullIfEmpty($row['start_date']),
                        'end_date' => $this->nullIfEmpty($row['end_date']),
                        'enrollment_status' => $this->nullIfEmpty($row['enrollment_status']) ?? AffiliationHistory::STATUS_ENROLLED,
                        'job_description' => $this->nullIfEmpty($row['job_description']),
                        'import_locked' => ((int) $row['import_locked']) === 1,
                    ];

                    if ($affiliation) {
                        $dirty = [];
                        foreach ($payload as $key => $value) {
                            $current = $affiliation->{$key};
                            if ($current instanceof \DateTimeInterface) {
                                $current = $current->format('Y-m-d');
                            }
                            if ($key === 'import_locked') {
                                $current = (bool) $current;
                            }
                            if ($current !== $value) {
                                $dirty[$key] = $value;
                            }
                        }

                        if ($dirty === []) {
                            continue;
                        }

                        $results[] = [
                            $email,
                            (string) $user->employee_id,
                            (string) $user->employee_id,
                            'affiliation:'.$affiliation->id.':'.implode('|', array_keys($dirty)),
                            $dryRun ? '復元予定' : '復元',
                        ];

                        if (! $dryRun) {
                            $affiliation->update($dirty);
                        }

                        $updatedAffiliations++;
                    } else {
                        $results[] = [
                            $email,
                            (string) $user->employee_id,
                            (string) $user->employee_id,
                            'affiliation:new:'.$row['id'],
                            $dryRun ? '復元予定' : '復元',
                        ];

                        if (! $dryRun) {
                            $created = new AffiliationHistory($payload);
                            $created->id = (int) $row['id'];
                            $created->save();
                        }

                        $updatedAffiliations++;
                    }
                }
            }
        });

        if ($results !== []) {
            $this->table(['メール', 'バックアップID', '現在ID', '復元項目', '結果'], $results);
        }

        if ($skipped !== []) {
            $this->newLine();
            $this->warn('スキップ: '.count($skipped).' 件');
            foreach ($skipped as $line) {
                $this->line($line);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: ユーザー復元 %d 件 / 所属復元 %d 件 / 変更なし %d 件 / スキップ %d 件',
            $dryRun ? 'dry-run' : '完了',
            $updatedUsers,
            $updatedAffiliations,
            $unchanged,
            count($skipped),
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function userChanges(User $user, array $row): array
    {
        $changes = [];

        foreach (['employee_id', 'name', 'last_name', 'first_name'] as $field) {
            $target = $row[$field];
            $current = (string) ($user->{$field} ?? '');
            if ($target !== '' && $current !== $target) {
                $changes[$field] = $target;
            }
        }

        return $changes;
    }

    /**
     * @return array<string, mixed>
     */
    private function profileChanges(?EmployeeProfile $profile, array $row): array
    {
        $changes = [];

        foreach (['name_kana', 'english_name', 'abbreviated_name', 'nationality'] as $field) {
            $target = $row[$field];
            $current = trim((string) ($profile?->{$field} ?? ''));
            if ($target !== $current) {
                $changes[$field] = $this->nullIfEmpty($target);
            }
        }

        $joinedAt = $row['joined_at'];
        $currentJoined = $profile?->joined_at?->toDateString() ?? '';
        if ($joinedAt !== $currentJoined) {
            $changes['joined_at'] = $this->nullIfEmpty($joinedAt);
        }

        return $changes;
    }

    /**
     * @return array<string, mixed>
     */
    private function detailChanges(?EmployeeHrDetail $detail, array $row): array
    {
        $changes = [];
        $stringFields = [
            'employment_status', 'employment_type', 'gender', 'remarks', 'jurisdiction',
            'name_kana_fullwidth', 'department_primary', 'section_primary', 'position_primary',
            'department_secondary', 'section_secondary', 'position_secondary',
            'company_phone', 'affiliation_code',
        ];

        foreach ($stringFields as $field) {
            $target = $row[$field];
            $current = trim((string) ($detail?->{$field} ?? ''));
            if ($target !== $current) {
                $changes[$field] = $this->nullIfEmpty($target);
            }
        }

        foreach (['resigned_at', 'last_working_day', 'birth_date'] as $field) {
            $target = $row[$field];
            $current = $detail?->{$field}?->toDateString() ?? '';
            if ($target !== $current) {
                $changes[$field] = $this->nullIfEmpty($target);
            }
        }

        return $changes;
    }

    /**
     * @param  list<string>  $headers
     * @return list<array<string, string>>
     */
    private function readCsv(string $path, array $headers): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("CSVを開けません: {$path}");
        }

        $rows = [];
        try {
            while (($data = fgetcsv($handle)) !== false) {
                if ($data === [null] || $data === []) {
                    continue;
                }

                if (count($data) < count($headers)) {
                    $data = array_pad($data, count($headers), '');
                }

                $row = [];
                foreach ($headers as $index => $header) {
                    $row[$header] = trim((string) ($data[$index] ?? ''));
                }

                if (($row['email'] ?? '') === '') {
                    continue;
                }

                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    private function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private function resolvePath(string $file): string
    {
        if (is_file($file)) {
            return $file;
        }

        $fromBase = base_path($file);

        return is_file($fromBase) ? $fromBase : $file;
    }
}
