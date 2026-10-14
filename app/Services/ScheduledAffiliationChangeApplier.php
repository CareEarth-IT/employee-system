<?php

namespace App\Services;

use App\Models\AffiliationHistory;
use App\Models\ScheduledAffiliationChange;
use App\Models\User;
use App\Support\AffiliationHrDetailSync;
use App\Support\ScheduledAffiliationChangeDiff;
use Illuminate\Support\Facades\DB;

class ScheduledAffiliationChangeApplier
{
    public function __construct(
        private DriveStaffSyncService $driveStaffSync,
    ) {}

    public function applyDue(?string $onDate = null): int
    {
        $onDate ??= now()->toDateString();
        $applied = 0;

        ScheduledAffiliationChange::query()
            ->due($onDate)
            ->orderBy('effective_date')
            ->orderBy('id')
            ->each(function (ScheduledAffiliationChange $change) use (&$applied): void {
                if ($this->apply($change)) {
                    $applied++;
                }
            });

        return $applied;
    }

    public function applyDueForUser(User $user, ?string $onDate = null): bool
    {
        $onDate ??= now()->toDateString();

        $change = ScheduledAffiliationChange::query()
            ->where('user_id', $user->id)
            ->due($onDate)
            ->orderBy('effective_date')
            ->orderBy('id')
            ->first();

        if ($change === null) {
            return false;
        }

        return $this->apply($change);
    }

    public function apply(ScheduledAffiliationChange $change): bool
    {
        if (! $change->isPending()) {
            return false;
        }

        if (! $change->isDue()) {
            return false;
        }

        $user = $change->user;
        if ($user === null) {
            return false;
        }

        DB::transaction(function () use ($change, $user): void {
            $effectiveDate = $change->effective_date?->toDateString() ?? now()->toDateString();
            $user->skipScheduledAffiliationApply();
            $current = $user->currentAffiliation();
            $resolved = ScheduledAffiliationChangeDiff::resolvedAffiliationAttributes($change, $current);

            $affiliation = $user->affiliationHistories()->create([
                'start_date' => $effectiveDate,
                'end_date' => null,
                'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                ...$resolved,
                'import_locked' => true,
            ]);

            $user->closeOtherEnrolledAffiliations($affiliation, $effectiveDate);

            $change->update([
                'status' => ScheduledAffiliationChange::STATUS_APPLIED,
                'applied_affiliation_id' => $affiliation->id,
                'applied_at' => now(),
            ]);

            AffiliationHrDetailSync::syncHrDetailFromAffiliation($user->fresh(), $affiliation->fresh());
            $user->syncRoleFromAffiliation();
        });

        $user->unsetRelation('affiliationHistories');
        $this->driveStaffSync->syncUser($user->fresh());

        return true;
    }
}
