<?php

namespace Tests\Unit;

use App\Models\EquipmentPurchaseApplication;
use App\Services\EquipmentPurchaseSubmissionPeriod;
use Carbon\Carbon;
use Tests\TestCase;

class EquipmentPurchaseSubmissionPeriodTest extends TestCase
{
    public function test_resolve_application_date_returns_actual_submission_date_during_grace_period(): void
    {
        $today = Carbon::parse('2026-08-04', 'Asia/Tokyo');

        $this->assertSame('2026-08-04', EquipmentPurchaseSubmissionPeriod::resolveApplicationDate($today));
        $this->assertSame('2026/07月', EquipmentPurchaseSubmissionPeriod::submissionTargetMonthLabel($today));
    }

    public function test_resolve_application_date_returns_actual_submission_date_in_target_month(): void
    {
        $today = Carbon::parse('2026-07-20', 'Asia/Tokyo');

        $this->assertSame('2026-07-20', EquipmentPurchaseSubmissionPeriod::resolveApplicationDate($today));
        $this->assertSame('2026/07月', EquipmentPurchaseSubmissionPeriod::submissionTargetMonthLabel($today));
    }

    public function test_application_month_label_uses_submission_target_month_not_application_date(): void
    {
        $application = new EquipmentPurchaseApplication([
            'application_date' => '2026-08-04',
            'created_at' => Carbon::parse('2026-08-04 10:15:00', 'Asia/Tokyo'),
        ]);

        $this->assertSame('2026/07月', $application->applicationMonthLabel());
        $this->assertSame('2026/08/04', $application->applicationDateDisplay());
    }
}
