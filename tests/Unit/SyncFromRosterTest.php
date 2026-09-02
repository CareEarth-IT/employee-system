<?php

namespace Tests\Unit;

use App\Console\Commands\SyncAffiliationCompanyFromRosterCommand;
use App\Console\Commands\SyncFromRosterCommand;
use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncFromRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_orchestrator_runs_all_roster_sync_steps(): void
    {
        $user = User::factory()->create([
            'name' => '西川 由希',
            'email' => 'yuki_nishikawa@careearth.info',
            'employee_id' => '10001',
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
            'joined_at' => '2026-06-24',
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => SyncAffiliationCompanyFromRosterCommand::BULK_IMPORT_START_DATE,
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '旧部署',
            'position' => '派遣',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_status' => '在籍中',
            'employment_type' => '派遣',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,短縮表示,ナマエ,ID,性別,国籍,備考,管轄,入社日,状況,雇用形態,所属,部署*,課/チーム*,役職【選択】,電話番号,社用アドレス
西川 由希,Nishikawa Yuki,西川,ニシカワユキ,10001,女性,日本,備考テスト,東京,2023/11/6,在籍,正社員,CE,管理本部,庶務課,一般,080-1111-2222,yuki_nishikawa@careearth.info
CSV
        );

        Artisan::call(SyncFromRosterCommand::class, [
            'file' => $path,
        ]);

        $user->refresh();
        $profile = $user->profile;
        $detail = $user->hrDetail;
        $affiliation = $user->currentAffiliation();

        $this->assertSame('2023-11-06', $profile?->joined_at?->toDateString());
        $this->assertSame('在籍', $detail?->employment_status);
        $this->assertSame('正社員', $detail?->employment_type);
        $this->assertSame('CE', $detail?->affiliation_code);
        $this->assertSame('管理本部', $detail?->department_primary);
        $this->assertSame('庶務課', $detail?->section_primary);
        $this->assertSame('一般', $detail?->position_primary);
        $this->assertSame('080-1111-2222', $detail?->company_phone);
        $this->assertSame('東京', $affiliation?->location);
        $this->assertSame('管理本部', $affiliation?->department);
        $this->assertSame('正社員', $affiliation?->position);
        $this->assertSame('CareEarth', $affiliation?->company);
        $this->assertSame('ニシカワユキ', $profile?->name_kana);
        $this->assertSame('備考テスト', $detail?->remarks);
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
