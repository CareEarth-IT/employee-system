<?php

namespace Tests\Unit;

use App\Console\Commands\SyncAffiliationResignationCommand;
use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncAffiliationResignationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_closes_enrolled_affiliation_for_resigned_users(): void
    {
        $user = User::factory()->create([
            'email' => 'resigned-fix@careearth.info',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_status' => '退職',
            'resigned_at' => '2024-08-31',
        ]);

        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-06-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => '不動産事業部',
        ]);

        Artisan::call(SyncAffiliationResignationCommand::class);

        $affiliation->refresh();

        $this->assertSame(AffiliationHistory::STATUS_RESIGNED, $affiliation->enrollment_status);
        $this->assertSame('2024-08-31', $affiliation->end_date?->toDateString());
        $this->assertNull($user->fresh()->currentAffiliation());
    }
}
