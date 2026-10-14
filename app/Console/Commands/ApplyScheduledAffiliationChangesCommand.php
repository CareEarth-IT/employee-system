<?php

namespace App\Console\Commands;

use App\Services\ScheduledAffiliationChangeApplier;
use Illuminate\Console\Command;

class ApplyScheduledAffiliationChangesCommand extends Command
{
    protected $signature = 'affiliations:apply-scheduled
        {--date= : 反映判定日 (Y-m-d)。省略時は本日}';

    protected $description = '反映日到来済みの異動・役職事前登録を所属履歴へ反映する';

    public function handle(ScheduledAffiliationChangeApplier $applier): int
    {
        $date = $this->option('date');
        if (is_string($date) && $date !== '') {
            $applied = $applier->applyDue($date);
        } else {
            $applied = $applier->applyDue();
        }

        $this->info("Applied {$applied} scheduled affiliation change(s).");

        return self::SUCCESS;
    }
}
