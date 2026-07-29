<?php

namespace App\Services;

use App\Models\MonthlyAffiliationRecord;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyAffiliationSnapshotService
{
    public function capture(string $yearMonth, ?User $capturedBy = null): int
    {
        if (! MonthlyAffiliationRecord::isValidYearMonth($yearMonth)) {
            throw new \InvalidArgumentException('year_month must be YYYY-MM format.');
        }

        $capturedAt = now();
        $rows = $this->buildRows($yearMonth, $capturedBy, $capturedAt);

        return DB::transaction(function () use ($yearMonth, $rows): int {
            MonthlyAffiliationRecord::query()
                ->where('year_month', $yearMonth)
                ->delete();

            foreach ($rows as $row) {
                MonthlyAffiliationRecord::create($row);
            }

            return count($rows);
        });
    }

    /**
     * @return list<string>
     */
    public function savedMonths(): array
    {
        return MonthlyAffiliationRecord::query()
            ->select('year_month')
            ->distinct()
            ->orderByDesc('year_month')
            ->pluck('year_month')
            ->all();
    }

    /**
     * @return Collection<int, MonthlyAffiliationRecord>
     */
    public function recordsForMonth(string $yearMonth): Collection
    {
        return MonthlyAffiliationRecord::query()
            ->where('year_month', $yearMonth)
            ->orderBy('last_name_sort')
            ->orderBy('name')
            ->get();
    }

    public function hasMonth(string $yearMonth): bool
    {
        return MonthlyAffiliationRecord::query()
            ->where('year_month', $yearMonth)
            ->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRows(string $yearMonth, ?User $capturedBy, \DateTimeInterface $capturedAt): array
    {
        $rows = [];

        $users = User::query()
            ->with(['profile', 'affiliationHistories'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        foreach ($users as $user) {
            $affiliation = $user->currentAffiliation();

            $rows[] = [
                'year_month' => $yearMonth,
                'user_id' => $user->id,
                'name' => $user->displayName(),
                'last_name_sort' => $user->last_name ?? $user->displayName(),
                'employee_id' => $user->employee_id,
                'location' => $affiliation?->location,
                'department' => $affiliation?->department,
                'section' => $affiliation?->section,
                'captured_by_user_id' => $capturedBy?->id,
                'captured_at' => $capturedAt,
            ];
        }

        return $rows;
    }
}
