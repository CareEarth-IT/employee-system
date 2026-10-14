<?php

namespace App\Support;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Support\Collection;

final class AffiliationResignationSync
{
    /**
     * 詳細情報の退職（退職日または状況=退職/辞退）を、在籍中の所属部署へ反映する。
     */
    public static function syncFromHrDetail(User $user, ?EmployeeHrDetail $detail = null): bool
    {
        $detail ??= $user->hrDetail;

        if ($detail === null) {
            return false;
        }

        $resignedAt = $detail->resigned_at?->toDateString();
        $status = EmploymentStatus::normalize(trim((string) $detail->employment_status));
        $isResignedStatus = in_array($status, ['退職', '辞退'], true);

        // 退職日がある、または状況が退職/辞退なら所属の「現在」を閉じる
        if ($resignedAt === null && ! $isResignedStatus) {
            return false;
        }

        $endDate = $resignedAt ?? now()->toDateString();
        $changed = false;

        $user->affiliationHistories()
            ->where('enrollment_status', AffiliationHistory::STATUS_ENROLLED)
            ->each(function (AffiliationHistory $affiliation) use ($endDate, &$changed): void {
                $start = $affiliation->start_date?->toDateString();

                // 退職日より後に作られた誤った在籍中所属は削除する
                if ($start !== null && $start > $endDate) {
                    $affiliation->delete();
                    $changed = true;

                    return;
                }

                $affiliation->closeAsResigned($endDate);
                $changed = true;
            });

        if (! $changed && $resignedAt !== null) {
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

        if ($resignedAt !== null) {
            self::ensureEmploymentStatusReflectsResignation($detail);
        }

        return $changed;
    }

    /**
     * 退職・辞退の詳細情報を持つ全社員の在籍中所属を閉じる。
     *
     * @return array{changed: int, scanned: int}
     */
    public static function syncAllResigned(): array
    {
        $changed = 0;
        $scanned = 0;

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
            ->chunkById(100, function (Collection $users) use (&$changed, &$scanned): void {
                foreach ($users as $user) {
                    $scanned++;

                    if (self::syncFromHrDetail($user, $user->hrDetail)) {
                        $changed++;
                    }
                }
            });

        return [
            'changed' => $changed,
            'scanned' => $scanned,
        ];
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
