<?php

namespace Tests\Unit;

use App\Console\Commands\SyncFromRosterCommand;
use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncAirtableFromRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_orchestrator_syncs_airtable_csv_format(): void
    {
        $user = User::factory()->create([
            'name' => '旧 名前',
            'email' => 'yuki@careearth.info',
            'employee_id' => '10001',
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
            'joined_at' => '2026-06-24',
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '旧部署',
            'position' => '派遣',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
社員番号,氏名,短縮表示,メールアドレス,拠点,部署,課,役職,社員種別,生年月日,性別,入社日,備考
12345,西川 由希,西川,yuki@careearth.info,東京,管理本部,庶務課,一般,正社員,1990/4/1,女性,2023/11/6,備考テスト
CSV
        );

        Artisan::call(SyncFromRosterCommand::class, [
            'file' => $path,
            '--match-email-only' => true,
        ]);

        $user->refresh();
        $profile = $user->profile;
        $detail = $user->hrDetail;
        $affiliation = $user->currentAffiliation();

        $this->assertSame('西川 由希', $user->name);
        $this->assertSame('12345', $user->employee_id);
        $this->assertSame('2023-11-06', $profile?->joined_at?->toDateString());
        $this->assertSame('正社員', $detail?->employment_type);
        $this->assertSame('管理本部', $detail?->department_primary);
        $this->assertSame('庶務課', $detail?->section_primary);
        $this->assertSame('一般', $detail?->position_primary);
        $this->assertSame('女性', $detail?->gender);
        $this->assertSame('1990-04-01', $detail?->birth_date?->toDateString());
        $this->assertSame('備考テスト', $detail?->remarks);
        $this->assertSame('東京', $affiliation?->location);
        $this->assertSame('管理本部', $affiliation?->department);
        $this->assertSame('正社員', $affiliation?->position);
        $this->assertSame('西川', $profile?->abbreviated_name);
    }

    private function writeRosterCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'roster_');
        $csvPath = $path.'.csv';
        rename($path, $csvPath);
        file_put_contents($csvPath, $contents);

        return $csvPath;
    }
}
