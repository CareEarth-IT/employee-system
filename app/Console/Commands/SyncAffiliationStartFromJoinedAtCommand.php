<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AffiliationStartDateAlignment;
use Illuminate\Console\Command;

class SyncAffiliationStartFromJoinedAtCommand extends Command
{
    /** @deprecated Use AffiliationStartDateAlignment::BULK_IMPORT_START_DATE */
    public const BULK_IMPORT_START_DATE = AffiliationStartDateAlignment::BULK_IMPORT_START_DATE;

    protected $signature = 'employee:sync-affiliation-start
        {--dry-run : 更新せず内容だけ表示}';

    protected $description = '所属部署の開始日を入社日に合わせる（部署・会社など他項目は変更しない）';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $results = [];
        $unchanged = 0;

        $users = User::query()
            ->with(['profile', 'affiliationHistories'])
            ->whereHas('profile', fn ($query) => $query->whereNotNull('joined_at'))
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            $joinedAt = $user->profile?->joined_at?->toDateString();

            if ($joinedAt === null) {
                continue;
            }

        $affiliationCount = $user->affiliationHistories->count();

            foreach ($user->affiliationHistories as $affiliation) {
                if (! AffiliationStartDateAlignment::shouldAlign(
                    $affiliation,
                    $joinedAt,
                    $affiliationCount,
                )) {
                    $unchanged++;

                    continue;
                }

                $current = $affiliation->start_date->toDateString();

                if ($dryRun) {
                    $results[] = [
                        $user->email,
                        $user->displayName(),
                        $current,
                        $joinedAt,
                        $affiliation->department ?? '—',
                        '更新予定',
                    ];

                    continue;
                }

                $affiliation->update(['start_date' => $joinedAt]);

                $results[] = [
                    $user->email,
                    $user->displayName(),
                    $current,
                    $joinedAt,
                    $affiliation->department ?? '—',
                    '更新',
                ];
            }
        }

        if ($results !== []) {
            $this->table(
                ['メール', '氏名', '現在の開始日', '入社日', '部', '結果'],
                $results,
            );
        }

        $updated = count(array_filter($results, fn (array $row) => in_array($row[5], ['更新', '更新予定'], true)));

        $stillBulkDate = \App\Models\AffiliationHistory::query()
            ->whereDate('start_date', AffiliationStartDateAlignment::BULK_IMPORT_START_DATE)
            ->count();

        $this->newLine();
        $this->info(sprintf(
            '%s: 更新 %d 件 / 変更なし %d 件',
            $dryRun ? 'dry-run' : '完了',
            $updated,
            $unchanged,
        ));
        $this->line('  部署・会社・役職など他項目は変更していません。');
        $this->line("  start_date={$this->bulkImportStartDateLabel()}: {$stillBulkDate} 件");

        return self::SUCCESS;
    }

    private function bulkImportStartDateLabel(): string
    {
        return AffiliationStartDateAlignment::BULK_IMPORT_START_DATE;
    }
}
