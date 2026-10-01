<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EmployeeRegistryService;
use App\Support\EmployeeIdRules;
use App\Support\EmployeeRosterCsv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportMissingFromRosterCommand extends Command
{
    protected $signature = 'employee:import-missing-from-roster
        {file=database/imports/employee-roster.csv : 社員名簿 CSV のパス}
        {--dry-run : 登録せず内容だけ表示}';

    protected $description = '社員名簿 CSV のうち、ポータル未登録（メール不一致）の社員を新規登録する';

    public function handle(EmployeeRegistryService $registry): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));

        if (! is_readable($path)) {
            $this->error("CSVが見つかりません: {$path}");

            return self::FAILURE;
        }

        try {
            $rows = EmployeeRosterCsv::readCreateRows($path);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $skippedExisting = 0;
        $skippedInvalid = 0;
        $results = [];
        $errors = [];

        foreach ($rows as $row) {
            if (! EmployeeIdRules::isValid($row['employee_id'])) {
                $skippedInvalid++;
                $errors[] = "行 {$row['line']}: 社員IDが不正のためスキップ {$row['email']} ID={$row['employee_id']}";

                continue;
            }

            if ($row['name'] === '') {
                $skippedInvalid++;
                $errors[] = "行 {$row['line']}: 氏名が空のためスキップ {$row['email']}";

                continue;
            }

            $existing = User::query()->where('email', $row['email'])->first();

            if ($existing) {
                $skippedExisting++;
                $results[] = [$row['email'], $row['name'], $row['employee_id'], $row['employment_status'], '既存（変更なし）'];

                continue;
            }

            if (User::query()->where('employee_id', $row['employee_id'])->exists()) {
                $skippedInvalid++;
                $errors[] = "行 {$row['line']}: 社員ID重複のためスキップ {$row['email']} ID={$row['employee_id']}";

                continue;
            }

            if ($dryRun) {
                $created++;
                $results[] = [$row['email'], $row['name'], $row['employee_id'], $row['employment_status'], '新規登録予定'];

                continue;
            }

            try {
                $this->createFromRow($registry, $row);
                $created++;
                $results[] = [$row['email'], $row['name'], $row['employee_id'], $row['employment_status'], '新規登録'];
            } catch (ValidationException $e) {
                $skippedInvalid++;
                $errors[] = "行 {$row['line']}: ".collect($e->errors())->flatten()->implode(' / ');
            } catch (\Throwable $e) {
                $skippedInvalid++;
                $errors[] = "行 {$row['line']}: {$e->getMessage()}";
            }
        }

        if ($results !== []) {
            $this->table(['メール', '氏名', 'ID', '状況', '結果'], $results);
        }

        if ($errors !== []) {
            $this->newLine();
            $this->warn('スキップ: '.count($errors).' 件');
            foreach ($errors as $error) {
                $this->line($error);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: 新規 %d 件 / 既存 %d 件 / スキップ %d 件',
            $dryRun ? 'dry-run' : '完了',
            $created,
            $skippedExisting,
            $skippedInvalid,
        ));
        $this->line('  社用アドレスがない行、および既に登録済みのメールは対象外です。');
        $this->line('  既存社員の入社日・状況更新は employee:sync-from-roster を使ってください。');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createFromRow(EmployeeRegistryService $registry, array $row): User
    {
        $payload = [
            'name' => $row['name'],
            'email' => $row['email'],
            'password' => User::DEFAULT_REGISTRY_PASSWORD,
            'employee_id' => $row['employee_id'],
            'department' => $row['department'],
            'company' => $row['company'],
            'section' => $row['section'] !== '' ? $row['section'] : null,
            'location' => $row['location'],
            'employment_type' => $row['employment_type'],
            'employment_status' => $row['employment_status'],
            'english_name' => $row['english_name'] !== '' ? $row['english_name'] : null,
            'abbreviated_name' => $row['abbreviated_name'] !== '' ? $row['abbreviated_name'] : null,
            'gender' => $row['gender'] !== '' ? $row['gender'] : null,
            'nationality' => $row['nationality'] !== '' ? $row['nationality'] : null,
            'remarks' => $row['remarks'] !== '' ? $row['remarks'] : null,
            'joined_at' => $row['joined_at'],
            'resigned_at' => $row['resigned_at'],
            'birth_date' => $row['birth_date'],
        ];

        $validator = Validator::make($payload, [
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'employee_id' => EmployeeIdRules::rules(required: true),
            'company' => ['required', 'string'],
            'location' => ['required', 'string'],
            'employment_type' => ['required', 'string'],
            'employment_status' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $registry->create($payload);
    }

    private function resolvePath(string $file): string
    {
        if (str_starts_with($file, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $file)) {
            return $file;
        }

        return base_path($file);
    }
}
