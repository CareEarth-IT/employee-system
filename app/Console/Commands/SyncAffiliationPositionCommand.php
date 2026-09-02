<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AffiliationPositionSync;
use Illuminate\Console\Command;

class SyncAffiliationPositionCommand extends Command
{
    protected $signature = 'employee:sync-affiliation-position
        {--dry-run : 更新せず内容だけ表示}';

    protected $description = '所属部署の役職から雇用形態ラベル（正社員・一般など）を除去し、人事詳細の役職①があれば反映する';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $results = [];
        $unchanged = 0;

        $users = User::query()
            ->with(['hrDetail', 'affiliationHistories'])
            ->whereHas('affiliationHistories', fn ($query) => $query->currentlyActive())
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            $affiliation = $user->currentAffiliation();

            if ($affiliation === null || ! AffiliationPositionSync::isInvalidPositionLabel($affiliation->position)) {
                $unchanged++;

                continue;
            }

            $current = trim((string) ($affiliation->position ?? ''));
            $target = AffiliationPositionSync::resolveCorrectedPosition($user);

            if ($current === ($target ?? '')) {
                $unchanged++;

                continue;
            }

            if ($dryRun) {
                $results[] = [
                    $user->email,
                    $user->displayName(),
                    $current !== '' ? $current : '—',
                    $target ?? '—',
                    $affiliation->start_date->toDateString(),
                    $affiliation->department ?? '—',
                    '更新予定',
                ];

                continue;
            }

            $affiliation->update(['position' => $target]);

            $results[] = [
                $user->email,
                $user->displayName(),
                $current !== '' ? $current : '—',
                $target ?? '—',
                $affiliation->start_date->toDateString(),
                $affiliation->department ?? '—',
                '更新',
            ];
        }

        if ($results !== []) {
            $this->table(
                ['メール', '氏名', '現在の役職', '修正後', '開始日', '部', '結果'],
                $results,
            );
        }

        $updated = count(array_filter(
            $results,
            fn (array $row) => in_array($row[6], ['更新', '更新予定'], true),
        ));

        $this->newLine();
        $this->info(sprintf(
            '%s: 更新 %d 件 / 変更なし %d 件',
            $dryRun ? 'dry-run' : '完了',
            $updated,
            $unchanged,
        ));
        $this->line('  部署・会社・開始日など他項目は変更していません。');

        return self::SUCCESS;
    }
}
