<?php

namespace App\Console\Commands;

use App\Services\EmployeeBulkImporter;
use Illuminate\Console\Command;

class ImportEmployeesCommand extends Command
{
    protected $signature = 'employee:import-bulk
        {file=database/imports/employees.csv : CSVファイルパス}
        {--dry-run : 登録せず内容だけ表示}
        {--limit= : 先頭N件だけ処理（ローカル確認用）}
        {--sync-users : 既存社員の氏名・社員番号も CSV で更新（import_locked 以外）}
        {--force-users : import_locked も含め氏名・社員番号を CSV で更新}
        {--sync-profiles : 既存プロフィールの名前カナ・国籍も CSV で更新（入社日は常に保持）}
        {--force-profiles : import_locked も含めプロフィールを CSV で更新}
        {--sync-affiliations : 既存の所属も CSV で上書き（import_locked 以外）}
        {--force-affiliations : import_locked も含め所属を CSV で上書き}';

    protected $description = '【一時用】CSVから社員を一括登録する（既存データは明示フラグがない限り一切上書きしない）';

    public function handle(EmployeeBulkImporter $importer): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));

        if (! is_readable($path)) {
            $this->error("CSVが見つかりません: {$path}");
            $this->line('Google Workspace の CSV を database/imports/employees.csv に置くか、');
            $this->line('database/imports/employees.csv.example をコピーして作成してください。');

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $workingPath = $path;

        if ($limit !== null && $limit !== '') {
            $workingPath = $this->limitCsv($path, max(1, (int) $limit));
            $this->line('limit: 先頭 '.(int) $limit.' 件のみ処理します。');
        }

        try {
            $result = $importer->import($workingPath, [
                'dry_run' => (bool) $this->option('dry-run'),
                'sync_users' => (bool) $this->option('sync-users'),
                'force_users' => (bool) $this->option('force-users'),
                'sync_profiles' => (bool) $this->option('sync-profiles'),
                'force_profiles' => (bool) $this->option('force-profiles'),
                'sync_affiliations' => (bool) $this->option('sync-affiliations'),
                'force_affiliations' => (bool) $this->option('force-affiliations'),
            ]);
        } finally {
            if ($workingPath !== $path) {
                @unlink($workingPath);
            }
        }

        if ($result->rows !== []) {
            $this->table(
                ['メール', '氏名', '社員番号', '部', '課', '役職', '結果'],
                $result->rows,
            );
        }

        if ($result->failed()) {
            foreach ($result->errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ($result->dryRun) {
            $this->info('dry-run のため DB は変更していません。');
        } else {
            $this->info('一括登録が完了しました。');
            $this->line('初回ログインはパスワード再設定画面からパスワードを設定してください。');
        }

        return self::SUCCESS;
    }

    private function resolvePath(string $file): string
    {
        if (str_starts_with($file, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $file)) {
            return $file;
        }

        return base_path($file);
    }

    private function limitCsv(string $path, int $limit): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("CSVを開けません: {$path}");
        }

        $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];
        $header = array_shift($lines) ?? '';
        $body = array_slice(array_values(array_filter($lines, fn ($line) => trim($line) !== '')), 0, $limit);
        $temp = tempnam(sys_get_temp_dir(), 'employees-limit-');
        file_put_contents($temp, $header."\n".implode("\n", $body)."\n");

        return $temp;
    }
}
