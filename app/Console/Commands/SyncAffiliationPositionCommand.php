<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AffiliationPositionSync;
use Illuminate\Console\Command;

class SyncAffiliationPositionCommand extends Command
{
    protected $signature = 'employee:sync-affiliation-position
        {--dry-run : 更新せず内容だけ表示}';

    protected $description = '所属・人事詳細の役職から雇用形態ラベル（正社員・一般など）を除去し、有効な役職①があれば反映する';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $affiliationResults = [];
        $hrDetailResults = [];
        $unchanged = 0;

        User::query()
            ->with(['hrDetail', 'affiliationHistories'])
            ->orderBy('id')
            ->chunkById(100, function ($users) use (
                $dryRun,
                &$affiliationResults,
                &$hrDetailResults,
                &$unchanged,
            ): void {
                foreach ($users as $user) {
                    $affiliationChanged = $this->syncAffiliationPosition($user, $dryRun, $affiliationResults);
                    $hrDetailChanged = $this->syncHrDetailPosition($user, $dryRun, $hrDetailResults);

                    if (! $affiliationChanged && ! $hrDetailChanged) {
                        $unchanged++;
                    }
                }
            });

        if ($affiliationResults !== []) {
            $this->info('所属部署の役職');
            $this->table(
                ['メール', '氏名', '現在の役職', '修正後', '開始日', '部', '結果'],
                $affiliationResults,
            );
            $this->newLine();
        }

        if ($hrDetailResults !== []) {
            $this->info('人事詳細の役職①');
            $this->table(
                ['メール', '氏名', '現在の役職①', '修正後', '結果'],
                $hrDetailResults,
            );
            $this->newLine();
        }

        $affiliationUpdated = count(array_filter(
            $affiliationResults,
            fn (array $row) => in_array($row[6], ['更新', '更新予定'], true),
        ));
        $hrDetailUpdated = count(array_filter(
            $hrDetailResults,
            fn (array $row) => in_array($row[4], ['更新', '更新予定'], true),
        ));

        $this->info(sprintf(
            '%s: 所属 %d 件 / 人事詳細 %d 件 / 変更なし %d 件',
            $dryRun ? 'dry-run' : '完了',
            $affiliationUpdated,
            $hrDetailUpdated,
            $unchanged,
        ));
        $this->line('  部署・会社・開始日など他項目は変更していません。');

        return self::SUCCESS;
    }

    /**
     * @param  list<list<string>>  $results
     */
    private function syncAffiliationPosition(User $user, bool $dryRun, array &$results): bool
    {
        $affiliation = $user->currentAffiliation();

        if ($affiliation === null || ! $affiliation->isEnrolled()) {
            return false;
        }

        if (! AffiliationPositionSync::isInvalidPositionLabel($affiliation->position)) {
            return false;
        }

        $current = trim((string) ($affiliation->position ?? ''));
        $target = AffiliationPositionSync::resolveCorrectedPosition($user);

        if ($current === ($target ?? '')) {
            return false;
        }

        $results[] = [
            $user->email,
            $user->displayName(),
            $current !== '' ? $current : '—',
            $target ?? '—',
            $affiliation->start_date->toDateString(),
            $affiliation->department ?? '—',
            $dryRun ? '更新予定' : '更新',
        ];

        if (! $dryRun) {
            $affiliation->update(['position' => $target]);
        }

        return true;
    }

    /**
     * @param  list<list<string>>  $results
     */
    private function syncHrDetailPosition(User $user, bool $dryRun, array &$results): bool
    {
        $detail = $user->hrDetail;

        if ($detail === null || ! AffiliationPositionSync::isInvalidPositionLabel($detail->position_primary)) {
            return false;
        }

        $current = trim((string) ($detail->position_primary ?? ''));
        $target = AffiliationPositionSync::resolveCorrectedPosition($user);

        if ($current === ($target ?? '')) {
            return false;
        }

        $results[] = [
            $user->email,
            $user->displayName(),
            $current !== '' ? $current : '—',
            $target ?? '—',
            $dryRun ? '更新予定' : '更新',
        ];

        if (! $dryRun) {
            $detail->update(['position_primary' => $target]);
        }

        return true;
    }
}
