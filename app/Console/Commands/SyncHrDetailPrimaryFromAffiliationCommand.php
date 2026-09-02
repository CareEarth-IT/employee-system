<?php

namespace App\Console\Commands;

use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Console\Command;

class SyncHrDetailPrimaryFromAffiliationCommand extends Command
{
    protected $signature = 'employee:sync-hr-detail-primary-from-affiliation
        {--dry-run : 更新せず内容だけ表示}';

    protected $description = '在籍中の所属部署（部・課/チーム）から、詳細情報の部署①・課/チーム①を一括反映する';

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

                    EmployeeHrDetail::syncPrimaryOrgFromAffiliation($user, $affiliation);
                    $results[] = $this->resultRow($user, $plan, '更新');
                }
            });

        if ($results !== []) {
            $this->table(
                ['メール', '氏名', '部署①', '課/チーム①', '結果'],
                $results,
            );
        }

        $updated = count(array_filter(
            $results,
            fn (array $result) => in_array($result[4], ['更新', '更新予定'], true),
        ));

        $this->newLine();
        $this->info(sprintf(
            '%s: 更新 %d 件 / 変更なし %d 件 / 在籍所属なし %d 件',
            $dryRun ? 'dry-run' : '完了',
            $updated,
            $unchanged,
            $skippedNoAffiliation,
        ));
        $this->line('  所属部署・詳細情報の他項目（役職・状況など）は変更していません。');

        return self::SUCCESS;
    }

    /**
     * @param  array{
     *     changed: bool,
     *     current: array{department_primary: ?string, section_primary: ?string},
     *     target: array{department_primary: ?string, section_primary: ?string},
     * }  $plan
     * @return list<string>
     */
    private function resultRow(User $user, array $plan, string $status): array
    {
        return [
            $user->email,
            $user->displayName(),
            $this->displayChange(
                $plan['current']['department_primary'],
                $plan['target']['department_primary'],
            ),
            $this->displayChange(
                $plan['current']['section_primary'],
                $plan['target']['section_primary'],
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
