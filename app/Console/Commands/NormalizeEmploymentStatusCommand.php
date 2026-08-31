<?php

namespace App\Console\Commands;

use App\Models\EmployeeHrDetail;
use App\Support\EmploymentStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeEmploymentStatusCommand extends Command
{
    protected $signature = 'employee:normalize-employment-status
        {--dry-run : 更新せず件数のみ表示}';

    protected $description = 'employee_hr_details.employment_status の 在籍中 等を 在籍 に正規化する';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $targets = EmployeeHrDetail::query()
            ->whereNotNull('employment_status')
            ->where('employment_status', '!=', '')
            ->get()
            ->filter(function (EmployeeHrDetail $detail): bool {
                $normalized = EmploymentStatus::normalize($detail->employment_status);

                return $normalized !== trim((string) $detail->employment_status);
            });

        if ($targets->isEmpty()) {
            $this->info('正規化が必要なレコードはありません。');

            return self::SUCCESS;
        }

        $this->info('対象: '.$targets->count().' 件');

        foreach ($targets as $detail) {
            $before = trim((string) $detail->employment_status);
            $after = EmploymentStatus::normalize($before);
            $user = $detail->user;

            $this->line(sprintf(
                '  user_id=%d %s: %s → %s',
                $detail->user_id,
                $user?->email ?? '(no email)',
                $before,
                $after,
            ));
        }

        if ($dryRun) {
            $this->warn('dry-run のため DB は変更していません。');

            return self::SUCCESS;
        }

        $updated = 0;

        DB::transaction(function () use ($targets, &$updated): void {
            foreach ($targets as $detail) {
                $before = trim((string) $detail->employment_status);
                $after = EmploymentStatus::normalize($before);

                if ($after === $before) {
                    continue;
                }

                $detail->update(['employment_status' => $after]);
                $updated++;
            }
        });

        $this->info("更新完了: {$updated} 件");

        return self::SUCCESS;
    }
}
