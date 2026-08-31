<?php

namespace Tests\Unit;

use App\Console\Commands\NormalizeEmploymentStatusCommand;
use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NormalizeEmploymentStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_normalizes_enrolled_status_to_active(): void
    {
        $user = User::factory()->create(['email' => 'sample@careearth.info']);
        $detail = EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_status' => '在籍中',
        ]);

        Artisan::call(NormalizeEmploymentStatusCommand::class);

        $this->assertSame('在籍', $detail->fresh()->employment_status);
    }

    public function test_bootstrap_for_user_stores_normalized_status(): void
    {
        $user = User::factory()->create();
        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '通信部',
            'location' => '大阪',
        ]);

        $detail = EmployeeHrDetail::bootstrapForUser($user->fresh());

        $this->assertSame('在籍', $detail->employment_status);
    }

    public function test_active_enrolled_status_appears_on_employee_index(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create([
            'last_name' => '対象',
            'first_name' => '太郎',
            'name' => '対象 太郎',
        ]);
        EmployeeHrDetail::create([
            'user_id' => $target->id,
            'employment_status' => '在籍中',
        ]);

        $this->actingAs($viewer)
            ->get(route('employees.index', ['status' => '在籍']))
            ->assertOk()
            ->assertSee('対象 太郎', false);
    }
}
