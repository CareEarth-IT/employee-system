<?php

namespace Tests\Unit;

use App\Console\Commands\SyncHrDetailPrimaryFromAffiliationCommand;
use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncHrDetailPrimaryFromAffiliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_syncs_department_primary_from_current_affiliation(): void
    {
        $user = User::factory()->create(['email' => 'sample@careearth.info']);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '人事部',
            'section' => '人事課',
            'position' => '一般',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'department_primary' => '旧部署',
            'section_primary' => '旧課',
        ]);

        Artisan::call(SyncHrDetailPrimaryFromAffiliationCommand::class);

        $detail = $user->fresh()->hrDetail;
        $this->assertSame('人事部', $detail?->department_primary);
        $this->assertSame('人事課', $detail?->section_primary);
    }

    public function test_command_dry_run_does_not_update_hr_detail(): void
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '営業部',
            'section' => '営業1課',
            'location' => '大阪',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'department_primary' => '旧部署',
            'section_primary' => null,
        ]);

        Artisan::call(SyncHrDetailPrimaryFromAffiliationCommand::class, [
            '--dry-run' => true,
        ]);

        $detail = $user->fresh()->hrDetail;
        $this->assertSame('旧部署', $detail?->department_primary);
        $this->assertNull($detail?->section_primary);
    }

    public function test_command_skips_user_without_current_affiliation(): void
    {
        $user = User::factory()->create();

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'department_primary' => '手入力部署',
        ]);

        Artisan::call(SyncHrDetailPrimaryFromAffiliationCommand::class);

        $this->assertSame('手入力部署', $user->fresh()->hrDetail?->department_primary);
    }

    public function test_command_syncs_food_sales_team_into_section_primary(): void
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => 'Food Sales部',
            'section' => '法人チーム',
            'location' => '大阪',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'department_primary' => 'Food Sales部',
            'section_primary' => null,
        ]);

        Artisan::call(SyncHrDetailPrimaryFromAffiliationCommand::class);

        $detail = $user->fresh()->hrDetail;
        $this->assertSame('Food Sales部', $detail?->department_primary);
        $this->assertSame('法人チーム', $detail?->section_primary);
    }
}
