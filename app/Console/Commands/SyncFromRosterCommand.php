<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncFromRosterCommand extends Command
{
    protected $signature = 'employee:sync-from-roster
        {file=database/imports/employee-roster.csv : 社員名簿 CSV のパス}
        {--dry-run : 更新せず内容だけ表示}
        {--match-email-only : 氏名不一致でもメール一致なら更新（要確認）}';

    protected $description = 'Airtable 社員名簿 CSV から社員ポータルへ全項目を一括反映する';

    /** @var list<class-string<Command>> */
    private const STEPS = [
        SyncRegistryIdentityFromRosterCommand::class,
        SyncJoinedAtFromRosterCommand::class,
        SyncAffiliationStartFromJoinedAtCommand::class,
        SyncHrDetailFromRosterCommand::class,
        SyncHrDetailOrgPrimaryFromRosterCommand::class,
        SyncAffiliationOrgFromRosterCommand::class,
        SyncAffiliationPositionCommand::class,
        SyncAffiliationCompanyFromRosterCommand::class,
        SyncCompanyPhoneFromRosterCommand::class,
    ];

    public function handle(): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));

        if (! is_readable($path)) {
            $this->error("CSVが見つかりません: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $matchEmailOnly = (bool) $this->option('match-email-only');

        $this->info('Airtable 社員名簿 CSV から社員ポータルへ反映します。');
        $this->line("CSV: {$path}");

        if ($dryRun) {
            $this->line('モード: dry-run（DB は変更しません）');
        }

        if ($matchEmailOnly) {
            $this->line('氏名不一致でもメール一致で更新します（--match-email-only）');
        }

        $this->newLine();
        $this->line('反映する項目:');
        $this->line('  社員番号・氏名・短縮表示・性別・生年月日・備考・拠点');
        $this->line('  入社日 → プロフィールの入社日（入社予定日は入社日が空のときのみ）');
        $this->line('  所属部署の開始日 → 入社日に合わせる（所属が1件、または一括取込日の誤設定のみ）');
        $this->line('  社員種別（雇用形態）・状況（列がある場合）');
        $this->line('  部署・課・役職（人事詳細）');
        $this->line('  拠点・部署・課・役職（在籍中の所属）');
        $this->line('  所属部署の役職 → 人事詳細の役職①に合わせる（雇用形態が誤って入っている場合）');
        $this->line('  所属会社（所属列がある場合）・社用電話（列がある場合）');
        $this->newLine();

        $options = [
            'file' => $path,
        ];

        if ($dryRun) {
            $options['--dry-run'] = true;
        }

        if ($matchEmailOnly) {
            $options['--match-email-only'] = true;
        }

        $failed = false;

        foreach (self::STEPS as $index => $commandClass) {
            $step = $index + 1;
            $commandName = (new $commandClass)->getName();

            $this->info("==> Step {$step}/".count(self::STEPS).": {$commandName}");
            $this->newLine();

            $exitCode = Artisan::call($commandClass, $options);
            $this->output->write(Artisan::output());

            if ($exitCode !== self::SUCCESS) {
                $failed = true;
                $this->error("Step {$step} が失敗しました: {$commandName}");

                break;
            }

            $this->newLine();
        }

        if ($failed) {
            $this->error('一括反映を中断しました。上記ステップまでの結果を確認してください。');

            return self::FAILURE;
        }

        $this->info($dryRun ? 'dry-run 完了。本番 DB は変更していません。' : 'Airtable 社員名簿の一括反映が完了しました。');

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
