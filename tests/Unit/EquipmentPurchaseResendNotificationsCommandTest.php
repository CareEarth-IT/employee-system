<?php

namespace Tests\Unit;

use App\Mail\EquipmentPurchaseApprovalRequested;
use App\Mail\EquipmentPurchaseSubmitted;
use App\Models\AffiliationHistory;
use App\Models\EquipmentPurchaseApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EquipmentPurchaseResendNotificationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_resend_pending_application_since_cutoff(): void
    {
        Mail::fake();

        $applicant = User::factory()->create([
            'email' => 'applicant@careearth.info',
        ]);

        AffiliationHistory::create([
            'user_id' => $applicant->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => '通信部',
        ]);

        $application = EquipmentPurchaseApplication::query()->create([
            'user_id' => $applicant->id,
            'application_type' => EquipmentPurchaseApplication::TYPE_INTERNAL_OVER_30K,
            'purchase_site' => 'Amazon',
            'purchase_site_url' => 'https://example.test/item',
            'product_name' => 'テスト備品',
            'quantity' => 1,
            'price_including_tax' => 35000,
            'purchase_reason' => 'テスト',
            'item_destination' => EquipmentPurchaseApplication::DESTINATION_DEPARTMENT_ALL,
            'delivery_destination' => 'osaka_2f',
            'purchase_urgency' => EquipmentPurchaseApplication::URGENCY_NO_RUSH,
            'application_date' => '2026-07-28',
            'status' => EquipmentPurchaseApplication::STATUS_PENDING,
            'created_at' => '2026-07-28 10:00:00',
            'updated_at' => '2026-07-28 10:00:00',
        ]);

        $this->artisan('equipment-purchase:resend-notifications', [
            '--since' => '2026-07-27',
        ])->assertSuccessful();

        Mail::assertSent(EquipmentPurchaseSubmitted::class, function (EquipmentPurchaseSubmitted $mail) use ($applicant) {
            return $mail->hasTo($applicant->email);
        });
        Mail::assertSent(EquipmentPurchaseApprovalRequested::class);
    }

    public function test_resend_skips_applications_before_cutoff(): void
    {
        Mail::fake();

        $applicant = User::factory()->create([
            'email' => 'old-applicant@careearth.info',
        ]);

        EquipmentPurchaseApplication::query()->create([
            'user_id' => $applicant->id,
            'application_type' => EquipmentPurchaseApplication::TYPE_INTERNAL_OVER_30K,
            'purchase_site' => 'Amazon',
            'purchase_site_url' => 'https://example.test/old',
            'product_name' => '旧申請',
            'quantity' => 1,
            'price_including_tax' => 35000,
            'purchase_reason' => 'テスト',
            'item_destination' => EquipmentPurchaseApplication::DESTINATION_DEPARTMENT_ALL,
            'delivery_destination' => 'osaka_2f',
            'purchase_urgency' => EquipmentPurchaseApplication::URGENCY_NO_RUSH,
            'application_date' => '2026-07-20',
            'status' => EquipmentPurchaseApplication::STATUS_PENDING,
            'created_at' => '2026-07-20 10:00:00',
            'updated_at' => '2026-07-20 10:00:00',
        ]);

        $this->artisan('equipment-purchase:resend-notifications', [
            '--since' => '2026-07-27',
        ])->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_resend_sends_applicant_only_for_processed_application(): void
    {
        Mail::fake();

        $applicant = User::factory()->create([
            'email' => 'done-applicant@careearth.info',
        ]);

        EquipmentPurchaseApplication::query()->create([
            'user_id' => $applicant->id,
            'application_type' => EquipmentPurchaseApplication::TYPE_INTERNAL_OVER_30K,
            'purchase_site' => 'Amazon',
            'purchase_site_url' => 'https://example.test/done',
            'product_name' => '処理済み',
            'quantity' => 1,
            'price_including_tax' => 35000,
            'purchase_reason' => 'テスト',
            'item_destination' => EquipmentPurchaseApplication::DESTINATION_DEPARTMENT_ALL,
            'delivery_destination' => 'osaka_2f',
            'purchase_urgency' => EquipmentPurchaseApplication::URGENCY_NO_RUSH,
            'application_date' => '2026-07-28',
            'status' => EquipmentPurchaseApplication::STATUS_APPROVED,
            'created_at' => '2026-07-28 11:00:00',
            'updated_at' => '2026-07-28 12:00:00',
        ]);

        $this->artisan('equipment-purchase:resend-notifications', [
            '--since' => '2026-07-27',
        ])->assertSuccessful();

        Mail::assertSent(EquipmentPurchaseSubmitted::class, 1);
        Mail::assertNotSent(EquipmentPurchaseApprovalRequested::class);
    }

    public function test_resend_approver_mail_for_processed_application(): void
    {
        Mail::fake();

        $applicant = User::factory()->create([
            'email' => 'done-applicant@careearth.info',
        ]);

        $application = EquipmentPurchaseApplication::query()->create([
            'user_id' => $applicant->id,
            'application_type' => EquipmentPurchaseApplication::TYPE_INTERNAL_UNDER_30K,
            'purchase_site' => 'Amazon',
            'purchase_site_url' => 'https://example.test/done',
            'product_name' => '処理済み',
            'quantity' => 1,
            'price_including_tax' => 5000,
            'purchase_reason' => 'テスト',
            'item_destination' => EquipmentPurchaseApplication::DESTINATION_DEPARTMENT_ALL,
            'delivery_destination' => 'osaka_2f',
            'purchase_urgency' => EquipmentPurchaseApplication::URGENCY_NO_RUSH,
            'application_date' => '2026-08-10',
            'status' => EquipmentPurchaseApplication::STATUS_APPROVED,
            'created_at' => '2026-08-10 10:00:00',
            'updated_at' => '2026-08-10 11:00:00',
        ]);

        $this->artisan('equipment-purchase:resend-notifications', [
            '--ids' => (string) $application->id,
            '--resend-approver-mail' => true,
        ])->assertSuccessful();

        Mail::assertNotSent(EquipmentPurchaseSubmitted::class);
        Mail::assertSent(EquipmentPurchaseApprovalRequested::class);
    }
}
