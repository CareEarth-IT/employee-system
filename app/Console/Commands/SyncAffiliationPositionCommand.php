<?php

namespace App\Console\Commands;

use App\Models\AffiliationHistory;
use Illuminate\Console\Command;

class SyncAffiliationPositionCommand extends Command
{
    public const FROM_POSITION = '一般';

    public const TO_POSITION = '正社員';

    protected $signature = 'employee:sync-affiliation-position
        {--dry-run : 更新せず内容だけ表示}';

    protected $description = '所属部署の役職「一般」を「正社員」に修正する（他項目は変更しない）';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $results = [];
        $unchanged = 0;

        $affiliations = AffiliationHistory::query()
            ->with('user')
            ->where('position', self::FROM_POSITION)
            ->orderBy('id')
            ->get();

        foreach ($affiliations as $affiliation) {
            $user = $affiliation->user;

            if ($dryRun) {
                $results[] = [
                    $user?->email ?? '—',
                    $user?->displayName() ?? '—',
                    self::FROM_POSITION,
                    self::TO_POSITION,
                    $affiliation->start_date->toDateString(),
                    $affiliation->department ?? '—',
                    '更新予定',
                ];

                continue;
            }

            $affiliation->update(['position' => self::TO_POSITION]);

            $results[] = [
                $user?->email ?? '—',
                $user?->displayName() ?? '—',
                self::FROM_POSITION,
                self::TO_POSITION,
                $affiliation->start_date->toDateString(),
                $affiliation->department ?? '—',
                '更新',
            ];
        }

        $stillIppan = AffiliationHistory::query()
            ->where('position', self::FROM_POSITION)
            ->count();

        if ($results !== []) {
            $this->table(
                ['メール', '氏名', '現在の役職', '修正後', '開始日', '部', '結果'],
                $results,
            );
        }

        $updated = count($results);

        $this->newLine();
        $this->info(sprintf(
            '%s: 更新 %d 件 / 変更なし %d 件',
            $dryRun ? 'dry-run' : '完了',
            $updated,
            $unchanged,
        ));
        $this->line('  部署・会社・開始日など他項目は変更していません。');
        $this->line('  position='.self::FROM_POSITION.": {$stillIppan} 件");

        return self::SUCCESS;
    }
}
