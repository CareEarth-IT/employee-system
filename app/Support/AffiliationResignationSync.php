<?php

namespace App\Support;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;

final class AffiliationResignationSync
{
    /**
     * 詳細情報の退職日を、在籍中の所属部署へ反映する。
     */
    public static function syncFromHrDetail(User $user, ?EmployeeHrDetail $detail = null): bool
    {
        $detail ??= $user->hrDetail;

        if ($detail === null) {
            return false;
        }

        $resignedAt = $detail->resigned_at?->toDateString();

        if ($resignedAt === null) {
            return false;
        }

        $changed = false;

        $user->affiliationHistories()
            ->where('enrollment_status', AffiliationHistory::STATUS_ENROLLED)
            ->each(function (AffiliationHistory $affiliation) use ($resignedAt, &$changed): void {
                $affiliation->closeAsResigned($resignedAt);
                $changed = true;
            });

        if (! $changed) {
            $latestResigned = $user->affiliationHistories()
                ->where('enrollment_status', AffiliationHistory::STATUS_RESIGNED)
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first();

            if ($latestResigned !== null && $latestResigned->end_date?->toDateString() !== $resignedAt) {
                $latestResigned->update(['end_date' => $resignedAt]);
                $changed = true;
            }
        }

        if ($changed) {
            $user->syncRoleFromAffiliation();
        }

        self::ensureEmploymentStatusReflectsResignation($detail);

        return $changed;
    }

    public static function ensureEmploymentStatusReflectsResignation(EmployeeHrDetail $detail): void
    {
        if ($detail->resigned_at === null) {
            return;
        }

        $status = trim((string) $detail->employment_status);

        if (in_array($status, ['退職', '辞退'], true)) {
            return;
        }

        $detail->update(['employment_status' => '退職']);
    }
}
