<?php

namespace Tests\Feature;

use App\Mail\SocialInsuranceCompanyThresholdReached;
use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\SocialInsuranceThresholdNotification;
use App\Models\User;
use App\Services\SocialInsuranceThresholdNotifier;
use App\Support\SocialInsuranceCompanyStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SocialInsuranceStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_section_can_view_social_insurance_stats_page(): void
    {
        $viewer = $this->userInAffiliation('人事部', '人事課');

        $this->actingAs($viewer)
            ->get(route('social-insurance-stats.index'))
            ->assertOk()
            ->assertSee('社会保険人数（所属会社別）', false)
            ->assertSee('CE', false)
            ->assertSee('GT', false)
            ->assertSee('EM', false)
            ->assertSee('ME', false)
            ->assertSee('CEVN', false)
            ->assertSee('Total', false);
    }

    public function test_regular_employee_cannot_view_social_insurance_stats_page(): void
    {
        $viewer = $this->userInAffiliation('営業部', '営業課');

        $this->actingAs($viewer)
            ->get(route('social-insurance-stats.index'))
            ->assertForbidden();
    }

    public function test_information_systems_cannot_view_social_insurance_stats_page(): void
    {
        $viewer = $this->userInAffiliation('情報システム部', '事業IT推進課');

        $this->actingAs($viewer)
            ->get(route('social-insurance-stats.index'))
            ->assertForbidden();
    }

    public function test_general_affairs_can_view_social_insurance_stats_page(): void
    {
        $viewer = $this->userInAffiliation('経理部', '総務課');

        $this->actingAs($viewer)
            ->get(route('social-insurance-stats.index'))
            ->assertOk();
    }

    public function test_counts_social_insurance_by_company(): void
    {
        $this->createInsuredEmployee('CareEarth');
        $this->createInsuredEmployee('CareEarth');
        $this->createInsuredEmployee('GROWTEC');
        $this->createInsuredEmployee('CareEarth', hasInsurance: false);

        $counts = collect(SocialInsuranceCompanyStats::countsByCompany())
            ->keyBy('code');

        $this->assertSame(
            ['CE', 'GT', 'EM', 'ME', 'CEVN'],
            collect(SocialInsuranceCompanyStats::countsByCompany())->pluck('code')->all(),
        );
        $this->assertSame(2, $counts['CE']['count']);
        $this->assertSame(1, $counts['GT']['count']);
        $this->assertSame(0, $counts['ME']['count']);
    }

    public function test_notifies_hr_and_general_affairs_when_company_reaches_threshold(): void
    {
        Mail::fake();

        $generalAffairs = $this->userInAffiliation('経理部', '総務課', [
            'email' => 'somu@careearth.info',
        ]);

        for ($i = 0; $i < SocialInsuranceCompanyStats::THRESHOLD; $i++) {
            $this->createInsuredEmployee('CareEarth');
        }

        app(SocialInsuranceThresholdNotifier::class)->notifyReachedCompanies();

        Mail::assertSent(SocialInsuranceCompanyThresholdReached::class, function (SocialInsuranceCompanyThresholdReached $mail) {
            return $mail->hasTo(SocialInsuranceThresholdNotifier::HR_NOTIFICATION_EMAIL)
                && $mail->hasTo('somu@careearth.info')
                && $mail->company === 'CareEarth'
                && $mail->count === SocialInsuranceCompanyStats::THRESHOLD;
        });

        $this->assertDatabaseHas('social_insurance_threshold_notifications', [
            'company' => 'CareEarth',
            'threshold' => SocialInsuranceCompanyStats::THRESHOLD,
        ]);

        Mail::fake();
        app(SocialInsuranceThresholdNotifier::class)->notifyReachedCompanies();
        Mail::assertNothingSent();

        $this->assertNotNull($generalAffairs->fresh());
    }

    public function test_employee_index_shows_social_insurance_stats_link_for_hr_section(): void
    {
        $viewer = $this->userInAffiliation('人事部', '人事課');

        $this->actingAs($viewer)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee(route('social-insurance-stats.index'), false)
            ->assertSee('社会保険人数', false);
    }

    private function createInsuredEmployee(string $company, bool $hasInsurance = true): User
    {
        $user = User::factory()->create();

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'has_social_insurance' => $hasInsurance,
            'employment_status' => '在籍',
            'employment_type' => 'アルバイト',
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => $company,
            'department' => '営業部',
            'section' => '営業1課',
            'location' => '大阪',
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $userAttributes
     */
    private function userInAffiliation(string $department, string $section, array $userAttributes = []): User
    {
        $user = User::factory()->create($userAttributes);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => $department,
            'section' => $section,
            'location' => '大阪',
            'company' => 'CareEarth',
        ]);

        return $user->fresh();
    }
}
