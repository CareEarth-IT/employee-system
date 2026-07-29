<?php

namespace Tests\Unit;

use App\Console\Commands\SyncAffiliationStartFromJoinedAtCommand;
use App\Models\AffiliationHistory;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncAffiliationStartFromJoinedAtTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_aligns_bulk_import_start_date_with_joined_at(): void
    {
        $user = User::factory()->create(['email' => 'sample@careearth.info']);
        EmployeeProfile::create([
            'user_id' => $user->id,
            'joined_at' => '2025-12-15',
        ]);
        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => SyncAffiliationStartFromJoinedAtCommand::BULK_IMPORT_START_DATE,
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '情報システム部',
            'section' => '事業IT推進課',
            'position' => '一般',
            'import_locked' => true,
        ]);

        Artisan::call(SyncAffiliationStartFromJoinedAtCommand::class);

        $affiliation->refresh();
        $this->assertSame('2025-12-15', $affiliation->start_date->toDateString());
        $this->assertSame('情報システム部', $affiliation->department);
        $this->assertTrue($affiliation->import_locked);
    }

    public function test_command_does_not_change_transfer_history_with_valid_start_date(): void
    {
        $user = User::factory()->create();
        EmployeeProfile::create([
            'user_id' => $user->id,
            'joined_at' => '2019-04-01',
        ]);

        $oldAffiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2019-04-01',
            'end_date' => '2023-03-31',
            'enrollment_status' => AffiliationHistory::STATUS_MOVED,
            'department' => '営業部',
            'location' => '大阪',
        ]);

        $currentAffiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2023-04-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '通信部',
            'location' => '大阪',
        ]);

        Artisan::call(SyncAffiliationStartFromJoinedAtCommand::class);

        $this->assertSame('2019-04-01', $oldAffiliation->fresh()->start_date->toDateString());
        $this->assertSame('2023-04-01', $currentAffiliation->fresh()->start_date->toDateString());
    }

    public function test_command_aligns_single_affiliation_when_dates_differ(): void
    {
        $user = User::factory()->create();
        EmployeeProfile::create([
            'user_id' => $user->id,
            'joined_at' => '2024-08-01',
        ]);

        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-09-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '人事部',
            'location' => '大阪',
        ]);

        Artisan::call(SyncAffiliationStartFromJoinedAtCommand::class);

        $this->assertSame('2024-08-01', $affiliation->fresh()->start_date->toDateString());
    }
}
