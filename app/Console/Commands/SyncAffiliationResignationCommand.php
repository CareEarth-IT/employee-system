<?php

namespace App\Console\Commands;

use App\Models\AffiliationHistory;
use App\Models\User;
use App\Support\AffiliationResignationSync;
use App\Support\EmploymentStatus;
use Illuminate\Console\Command;

class SyncAffiliationResignationCommand extends Command
{
    protected $signature = 'employee:sync-affiliation-resignation
        {--dry-run : 更新せず対象だけ表示}';

    protected $description = '退職・辞退者の在籍中所属を終了日付きの退職へ閉じる（誤って「現在」になっている所属を修正）';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $targets = [];

        User::query()
            ->whereHas('hrDetail', function ($query): void {
                $query->where(function ($statusQuery): void {
                    $statusQuery
                        ->whereIn('employment_status', ['退職', '辞退'])
                        ->orWhereNotNull('resigned_at');
                });
            })
            ->with(['hrDetail', 'affiliationHistories'])
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$targets): void {
                foreach ($users as $user) {
                    $enrolled = $user->affiliationHistories
                        ->where('enrollment_status', AffiliationHistory::STATUS_ENROLLED)
                        ->values();

                    if ($enrolled->isEmpty()) {
                        continue;
                    }

                    $detail = $user->hrDetail;
                    $endDate = $detail?->resigned_at?->toDateString();
                    $status = EmploymentStatus::normalize(trim((string) ($detail?->employment_status ?? '')));

                    foreach ($enrolled as $affiliation) {
                        $start = $affiliation->start_date?->toDateString() ?? '—';
                        $action = ($endDate !== null && $start !== '—' && $start > $endDate) ? '削除' : '退職へ閉じる';

                        $targets[] = [
                            $user->email ?? '—',
                            $user->displayName(),
                            $status !== '' ? $status : '—',
                            $endDate ?? '—',
                            $start,
                            $affiliation->company ?? '—',
                            $affiliation->department ?? '—',
                            $action,
                        ];
                    }
                }
            });

        if ($targets === []) {
            $this->info('修正が必要な在籍中所属はありません。');

            return self::SUCCESS;
        }

        $this->table(
            ['メール', '氏名', '状況', '終了日', '開始日', '会社', '部署', '処理'],
            $targets,
        );
        $this->info('対象所属: '.count($targets).' 件');

        if ($dryRun) {
            $this->warn('dry-run のため DB は変更していません。');

            return self::SUCCESS;
        }

        $result = AffiliationResignationSync::syncAllResigned();

        $this->info(sprintf(
            '完了: 修正 %d 人 / 走査 %d 人',
            $result['changed'],
            $result['scanned'],
        ));

        return self::SUCCESS;
    }
}
