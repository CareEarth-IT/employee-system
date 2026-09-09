<?php

namespace App\Console\Commands;

use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Console\Command;

class SyncHrDetailPrimaryFromAffiliationCommand extends Command
{
    protected $signature = 'employee:sync-hr-detail-primary-from-affiliation
        {--dry-run : 更新せず内容だけ表示}';

    protected $description = '詳細情報を正として、在籍中の所属部署1件だけに組織項目を一括反映する（過去の所属履歴は変更しない）';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $results = [];
        $unchanged = 0;
        $skippedNoAffiliation = 0;

        User::query()
            ->with(['hrDetail', 'affiliationHistories'])
            ->orderBy('id')
            ->chunkById(100, function ($users) use (
                $dryRun,
                &$results,
                &$unchanged,
                &$skippedNoAffiliation,
            ): void {
                foreach ($users as $user) {
                    $plan = EmployeeHrDetail::primaryOrgSyncPlan($user);

                    if ($plan === null) {
                        $skippedNoAffiliation++;

                        continue;
                    }

                    if (! $plan['changed']) {
                        $unchanged++;

                        continue;
                    }

                    $affiliation = $user->currentAffiliation();

                    if ($dryRun) {
                        $results[] = $this->resultRow($user, $plan, '更新予定');

                        continue;
                    }

                    EmployeeHrDetail::syncPrimaryOrgFromAffiliation($user);
                    $results[] = $this->resultRow($user, $plan, '更新');
                }
            });

        if ($results !== []) {
            $this->table(
                ['メール', '氏名', '所属会社', '管轄', '部', '課/チーム', '役職', '結果'],
                $results,
            );
        }

        $updated = count(array_filter(
            $results,
            fn (array $result) => in_array($result[7], ['更新', '更新予定'], true),
        ));

        $this->newLine();
        $this->info(sprintf(
            '%s: 更新 %d 件 / 変更なし %d 件 / 在籍所属なし %d 件',
            $dryRun ? 'dry-run' : '完了',
            $updated,
            $unchanged,
            $skippedNoAffiliation,
        ));
        $this->line('  詳細情報を正として、在籍中の所属部署1件のみ更新します。過去の所属履歴は変更しません。');

        return self::SUCCESS;
    }

    /**
     * @param  array{
     *     changed: bool,
     *     current: array<string, ?string>,
     *     target: array<string, ?string>,
     * }  $plan
     * @return list<string>
     */
    private function resultRow(User $user, array $plan, string $status): array
    {
        return [
            $user->email,
            $user->displayName(),
            $this->displayChange(
                $plan['current']['company'],
                $plan['target']['company'],
            ),
            $this->displayChange(
                $plan['current']['location'],
                $plan['target']['location'],
            ),
            $this->displayChange(
                $plan['current']['department'],
                $plan['target']['department'],
            ),
            $this->displayChange(
                $plan['current']['section'],
                $plan['target']['section'],
            ),
            $this->displayChange(
                $plan['current']['position'],
                $plan['target']['position'],
            ),
            $status,
        ];
    }

    private function displayChange(?string $current, ?string $target): string
    {
        $currentLabel = $this->displayValue($current);
        $targetLabel = $this->displayValue($target);

        if ($currentLabel === $targetLabel) {
            return $targetLabel;
        }

        return "{$currentLabel} -> {$targetLabel}";
    }

    private function displayValue(?string $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : '—';
    }
}
