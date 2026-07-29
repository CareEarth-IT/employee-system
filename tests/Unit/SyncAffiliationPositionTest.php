<?php

namespace Tests\Unit;

use App\Console\Commands\SyncAffiliationPositionCommand;
use App\Models\AffiliationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncAffiliationPositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_updates_position_ippan_to_seishain(): void
    {
        $user = User::factory()->create(['email' => 'sample@careearth.info']);

        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-04-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '情報システム部',
            'position' => SyncAffiliationPositionCommand::FROM_POSITION,
            'import_locked' => true,
        ]);

        Artisan::call(SyncAffiliationPositionCommand::class);

        $affiliation->refresh();
        $this->assertSame(SyncAffiliationPositionCommand::TO_POSITION, $affiliation->position);
        $this->assertSame('情報システム部', $affiliation->department);
        $this->assertTrue($affiliation->import_locked);
    }

    public function test_command_does_not_change_other_positions(): void
    {
        $user = User::factory()->create();

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

        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-04-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'location' => '大阪',
            'position' => SyncAffiliationPositionCommand::FROM_POSITION,
        ]);

        Artisan::call(SyncAffiliationPositionCommand::class, ['--dry-run' => true]);

        $this->assertSame(SyncAffiliationPositionCommand::FROM_POSITION, $affiliation->fresh()->position);
    }
}
