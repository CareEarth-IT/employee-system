<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\AffiliationHrDetailSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliationHrDetailSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncs_current_affiliation_from_hr_detail_org_fields(): void
    {
        $user = User::factory()->create();

        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'GROWTEC',
            'location' => '東京',
            'department' => '旧部署',
            'section' => '旧課',
            'position' => '部長',
        ]);

        $detail = EmployeeHrDetail::create([
            'user_id' => $user->id,
            'affiliation_code' => 'CE',
            'jurisdiction' => '大阪',
            'department_primary' => '人事部',
            'section_primary' => '人事課',
            'position_primary' => '部長',
        ]);

        AffiliationHrDetailSync::syncAffiliationFromHrDetail($user->fresh(), $detail);

        $affiliation->refresh();
        $this->assertSame('CareEarth', $affiliation->company);
        $this->assertSame('大阪', $affiliation->location);
        $this->assertSame('人事部', $affiliation->department);
        $this->assertSame('人事課', $affiliation->section);
        $this->assertSame('部長', $affiliation->position);
    }

    public function test_syncs_each_concurrent_affiliation_from_primary_and_secondary(): void
    {
        $user = User::factory()->create();

        $first = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '通信事業部',
            'section' => '通信課',
            'position' => '一般',
        ]);
        $second = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2025-04-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '東京',
            'department' => '食品事業部',
            'section' => '店舗運営課',
            'position' => '一般',
        ]);

        $detail = EmployeeHrDetail::create([
            'user_id' => $user->id,
            'affiliation_code' => 'CE',
            'jurisdiction' => '大阪',
            'department_primary' => '通信事業部',
            'section_primary' => '事業推進課',
            'position_primary' => '課長',
            'department_secondary' => '不動産部',
            'section_secondary' => '営業課',
            'position_secondary' => '係長',
        ]);

        AffiliationHrDetailSync::syncAffiliationFromHrDetail($user->fresh(), $detail);

        $first->refresh();
        $second->refresh();
        $this->assertSame('事業推進課', $first->section);
        $this->assertSame('課長', $first->position);
        $this->assertSame('不動産部', $second->department);
        $this->assertSame('営業課', $second->section);
        $this->assertSame('係長', $second->position);
        $this->assertSame('東京', $second->location);
    }

    public function test_import_sync_updates_hr_detail_without_touching_past_affiliations(): void
    {
        $user = User::factory()->create();

        $pastAffiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2020-01-01',
            'end_date' => '2023-12-31',
            'enrollment_status' => AffiliationHistory::STATUS_MOVED,
            'department' => '過去部署',
            'section' => '過去課',
            'location' => '東京',
        ]);

        $currentAffiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '情報システム部',
            'section' => '情報システム課',
            'position' => '課長',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'department_primary' => '旧詳細部署',
        ]);

        AffiliationHrDetailSync::syncHrDetailFromAffiliation($user->fresh(), $currentAffiliation);

        $pastAffiliation->refresh();
        $detail = $user->fresh()->hrDetail;

        $this->assertSame('過去部署', $pastAffiliation->department);
        $this->assertSame('過去課', $pastAffiliation->section);
        $this->assertSame('情報システム部', $detail?->department_primary);
        $this->assertSame('情報システム課', $detail?->section_primary);
        $this->assertSame('CE', $detail?->affiliation_code);
    }
}
