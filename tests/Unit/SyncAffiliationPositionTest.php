<?php

namespace Tests\Unit;

use App\Console\Commands\SyncAffiliationPositionCommand;
use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncAffiliationPositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_replaces_employment_type_with_hr_detail_position(): void
    {
        $user = User::factory()->create(['email' => 'sample@careearth.info']);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_type' => '正社員',
            'position_primary' => 'リーダー',
        ]);

        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-04-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '大阪グローバル事業部',
            'position' => '正社員',
            'import_locked' => true,
        ]);

        Artisan::call(SyncAffiliationPositionCommand::class);

        $affiliation->refresh();
        $this->assertSame('リーダー', $affiliation->position);
        $this->assertSame('大阪グローバル事業部', $affiliation->department);
        $this->assertTrue($affiliation->import_locked);
    }

    public function test_command_does_not_change_valid_position(): void
    {
        $user = User::factory()->create();

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_type' => '正社員',
            'position_primary' => 'リーダー',
        ]);

        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-04-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'location' => '大阪',
            'position' => '部長',
        ]);

        Artisan::call(SyncAffiliationPositionCommand::class);

        $this->assertSame('部長', $affiliation->fresh()->position);
    }

    public function test_dry_run_does_not_update(): void
    {
        $user = User::factory()->create();

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_type' => '正社員',
            'position_primary' => 'リーダー',
        ]);

        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-04-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'location' => '大阪',
            'position' => '正社員',
        ]);

        Artisan::call(SyncAffiliationPositionCommand::class, ['--dry-run' => true]);

        $this->assertSame('正社員', $affiliation->fresh()->position);
    }

    public function test_command_clears_invalid_position_when_hr_detail_has_no_position(): void
    {
        $user = User::factory()->create();

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_type' => '正社員',
        ]);

        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-04-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'location' => '東京',
            'position' => '一般',
        ]);

        Artisan::call(SyncAffiliationPositionCommand::class);

        $this->assertNull($affiliation->fresh()->position);
    }
}
