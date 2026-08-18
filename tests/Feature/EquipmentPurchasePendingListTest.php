<?php

namespace Tests\Feature;

use App\Models\AffiliationHistory;
use App\Models\EquipmentPurchaseApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentPurchasePendingListTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_purchased_onsite_and_internal_when_only_section_matches(): void
    {
        $manager = $this->makeUser('manager@careearth.info', '大阪営業部', '部長');
        $applicant = $this->makeUser('applicant@careearth.info', '通信部', '一般');

        $purchased = $this->makeApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_PURCHASED_OVER_10K,
            40000,
            department: null,
            section: '大阪営業部',
            itemDestination: EquipmentPurchaseApplication::DESTINATION_SECTION_ONLY,
        );
        $onsite = $this->makeApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: null,
            section: '大阪営業部',
            itemDestination: EquipmentPurchaseApplication::DESTINATION_SECTION_ONLY,
        );
        $internal = $this->makeApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_INTERNAL_UNDER_30K,
            20000,
            department: '通信部',
            section: null,
        );

        $this->assertTrue($manager->canApproveEquipmentPurchase($purchased));
        $this->assertTrue($manager->canApproveEquipmentPurchase($onsite));
        $this->assertFalse($manager->canApproveEquipmentPurchase($internal));

        $response = $this->actingAs($manager)
            ->get(route('equipment-purchases.pending'))
            ->assertOk();

        $response->assertSee($purchased->product_name, false);
        $response->assertSee($onsite->product_name, false);
        $response->assertDontSee($internal->product_name, false);
    }

    public function test_manager_sees_onsite_over_30k_matched_by_applicant_affiliation(): void
    {
        $manager = $this->makeUser('manager@careearth.info', '大阪営業部', '部長');
        $applicant = $this->makeUser('applicant@careearth.info', '大阪営業部', '一般');

        $application = $this->makeApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            45000,
            department: null,
            section: null,
            itemDestination: EquipmentPurchaseApplication::DESTINATION_ONSITE,
            onsiteName: 'テスト現場',
        );

        $this->assertTrue($manager->canApproveEquipmentPurchase($application));

        $this->actingAs($manager)
            ->get(route('equipment-purchases.pending'))
            ->assertOk()
            ->assertSee($application->product_name, false);
    }

    public function test_food_emergency_over_30k_appears_for_designated_approver_via_affiliation_only(): void
    {
        $approver = $this->makeUser('buicuongthinh@careearth.info', '大阪グローバル事業部', '執行役員');
        $applicant = $this->makeUser('food@careearth.info', '食品事業部', '一般');
        $manager = $this->makeUser('manager@careearth.info', '大阪営業部', '部長');

        $application = $this->makeApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_PURCHASED_OVER_10K,
            40000,
            department: null,
            section: null,
            itemDestination: EquipmentPurchaseApplication::DESTINATION_ONSITE,
            onsiteName: '食品現場',
            deliveryDestination: 'osaka_2f',
        );

        $this->assertTrue($application->isFoodRelatedApplication());
        $this->assertTrue($approver->canApproveEquipmentPurchase($application));
        $this->assertFalse($manager->canApproveEquipmentPurchase($application));

        $this->actingAs($approver)
            ->get(route('equipment-purchases.pending'))
            ->assertOk()
            ->assertSee($application->product_name, false);

        $this->actingAs($manager)
            ->get(route('equipment-purchases.pending'))
            ->assertOk()
            ->assertDontSee($application->product_name, false);
    }

    public function test_food_momotani_over_30k_onsite_type_appears_for_designated_approver(): void
    {
        $approver = $this->makeUser('nguyenphuong_tien@careearth.info', '食品事業部', '一般', '店舗運営課');
        $applicant = $this->makeUser('food@careearth.info', '食品事業部', '一般');

        $application = $this->makeApplication(
            $applicant,
            EquipmentPurchaseApplication::TYPE_ONSITE_OVER_30K,
            40000,
            department: '食品事業部',
            deliveryDestination: EquipmentPurchaseApplication::DELIVERY_FOOD_MOMOTANI,
        );

        $this->assertTrue($approver->canApproveEquipmentPurchase($application));

        $this->actingAs($approver)
            ->get(route('equipment-purchases.pending'))
            ->assertOk()
            ->assertSee($application->product_name, false);
    }

    private function makeUser(
        string $email,
        string $department,
        string $position,
        ?string $section = null,
        string $location = '大阪',
    ): User {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $email,
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'department' => $department,
            'section' => $section,
            'position' => $position,
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => null,
            'location' => $location,
        ]);

        return $user->fresh(['affiliationHistories']);
    }

    private function makeApplication(
        User $user,
        string $type,
        int $price,
        ?string $department = null,
        ?string $section = null,
        string $itemDestination = EquipmentPurchaseApplication::DESTINATION_DEPARTMENT_ALL,
        ?string $onsiteName = null,
        string $deliveryDestination = 'osaka_2f',
    ): EquipmentPurchaseApplication {
        return EquipmentPurchaseApplication::create([
            'user_id' => $user->id,
            'application_type' => $type,
            'purchase_site' => 'Amazon',
            'purchase_site_url' => 'https://example.test/item',
            'product_name' => 'テスト備品-'.$type.'-'.$price,
            'quantity' => 1,
            'price_including_tax' => $price,
            'purchase_reason' => 'テスト',
            'item_destination' => $itemDestination,
            'department' => $department,
            'section' => $section,
            'onsite_name' => $onsiteName,
            'delivery_destination' => $deliveryDestination,
            'purchase_urgency' => EquipmentPurchaseApplication::URGENCY_NO_RUSH,
            'application_date' => now()->toDateString(),
            'status' => EquipmentPurchaseApplication::STATUS_PENDING,
        ]);
    }
}
