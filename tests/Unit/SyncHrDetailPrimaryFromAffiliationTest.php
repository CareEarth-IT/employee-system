<?php

namespace Tests\Unit;

use App\Console\Commands\SyncHrDetailPrimaryFromAffiliationCommand;
use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\AffiliationHrDetailSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncHrDetailPrimaryFromAffiliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_syncs_current_affiliation_from_hr_detail(): void
    {
        $user = User::factory()->create(['email' => 'sample@careearth.info']);

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

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'affiliation_code' => 'CE',
            'jurisdiction' => '大阪',
            'department_primary' => '人事部',
            'section_primary' => '人事課',
            'position_primary' => '課長',
        ]);

        Artisan::call(SyncHrDetailPrimaryFromAffiliationCommand::class);

        $affiliation->refresh();
        $this->assertSame('CareEarth', $affiliation->company);
        $this->assertSame('大阪', $affiliation->location);
        $this->assertSame('人事部', $affiliation->department);
        $this->assertSame('人事課', $affiliation->section);
        $this->assertSame('課長', $affiliation->position);
    }

    public function test_command_dry_run_does_not_update_current_affiliation(): void
    {
        $user = User::factory()->create();

        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '営業部',
            'section' => '営業1課',
            'location' => '大阪',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'department_primary' => '人事部',
            'section_primary' => '人事課',
            'jurisdiction' => '大阪',
        ]);

        Artisan::call(SyncHrDetailPrimaryFromAffiliationCommand::class, [
            '--dry-run' => true,
        ]);

        $affiliation->refresh();
        $this->assertSame('営業部', $affiliation->department);
        $this->assertSame('営業1課', $affiliation->section);
    }

    public function test_command_skips_user_without_current_affiliation(): void
    {
        $user = User::factory()->create();

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'department_primary' => '人事部',
        ]);

        Artisan::call(SyncHrDetailPrimaryFromAffiliationCommand::class);

        $this->assertDatabaseCount('affiliation_histories', 0);
    }

    public function test_command_does_not_modify_past_affiliation_history(): void
    {
        $user = User::factory()->create();

        $pastAffiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2020-01-01',
            'end_date' => '2023-12-31',
            'enrollment_status' => AffiliationHistory::STATUS_MOVED,
            'company' => 'GROWTEC',
            'location' => '東京',
            'department' => '過去部署',
            'section' => '過去課',
            'position' => '一般',
        ]);

        $currentAffiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'GROWTEC',
            'location' => '東京',
            'department' => '旧部署',
            'section' => '旧課',
            'position' => '部長',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'affiliation_code' => 'CE',
            'jurisdiction' => '大阪',
            'department_primary' => '情報システム部',
            'section_primary' => '情報システム課',
            'position_primary' => '課長',
        ]);

        AffiliationHrDetailSync::syncAffiliationFromHrDetail($user->fresh());

        $pastAffiliation->refresh();
        $currentAffiliation->refresh();

        $this->assertSame('過去部署', $pastAffiliation->department);
        $this->assertSame('過去課', $pastAffiliation->section);
        $this->assertSame('情報システム部', $currentAffiliation->department);
        $this->assertSame('情報システム課', $currentAffiliation->section);
    }
}
