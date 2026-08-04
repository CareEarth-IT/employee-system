<?php

namespace App\Services;

use Carbon\Carbon;

class EquipmentPurchaseSubmissionPeriod
{
    /**
     * 申請対象月の翌月2営業日まで申請可能。
     */
    public static function canSubmitToday(?Carbon $today = null): bool
    {
        $today = self::normalizeDate($today ?? now());

        return $today->lte(self::submissionDeadlineForMonth(self::submissionTargetMonth($today)));
    }

    public static function submissionTargetMonth(?Carbon $today = null): Carbon
    {
        $today = self::normalizeDate($today ?? now());
        $monthStart = $today->copy()->startOfMonth();
        $previousMonthStart = $monthStart->copy()->subMonth();

        if ($today->lte(self::submissionDeadlineForMonth($previousMonthStart))) {
            return $previousMonthStart;
        }

        return $monthStart;
    }

    public static function resolveApplicationDate(?Carbon $today = null): string
    {
        return self::normalizeDate($today ?? now())->toDateString();
    }

    public static function submissionDeadlineForMonth(Carbon $targetMonth): Carbon
    {
        return self::secondBusinessDayOfMonth(
            $targetMonth->copy()->startOfMonth()->addMonth()
        )->endOfDay();
    }

    public static function deadlineMessage(?Carbon $today = null): string
    {
        $today = self::normalizeDate($today ?? now());
        $targetMonth = self::submissionTargetMonth($today);
        $deadline = self::submissionDeadlineForMonth($targetMonth);

        return sprintf(
            '%s分の申請は %s（翌月2営業日）までです。',
            $targetMonth->format('Y年n月'),
            $deadline->format('Y年n月j日')
        );
    }

    public static function closedMessage(): string
    {
        $today = self::normalizeDate(now());
        $targetMonth = self::submissionTargetMonth($today);
        $deadline = self::submissionDeadlineForMonth($targetMonth);

        return sprintf(
            '%s分の申請期限（%s）を過ぎているため、申請できません。',
            $targetMonth->format('Y年n月'),
            $deadline->format('Y年n月j日')
        );
    }

    public static function submissionTargetMonthLabel(?Carbon $today = null): string
    {
        return self::submissionTargetMonth($today)->format('Y/m').'月';
    }

    public static function secondBusinessDayOfMonth(Carbon $monthStart): Carbon
    {
        $date = $monthStart->copy()->startOfMonth();
        $businessDays = 0;

        while ($businessDays < 2) {
            if (! $date->isWeekend()) {
                $businessDays++;
            }

            if ($businessDays < 2) {
                $date->addDay();
            }
        }

        return $date->startOfDay();
    }

    private static function normalizeDate(Carbon $date): Carbon
    {
        return $date->copy()->timezone(config('app.timezone'))->startOfDay();
    }
}
