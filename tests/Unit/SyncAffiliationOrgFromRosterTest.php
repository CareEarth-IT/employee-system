<?php

namespace Tests\Unit;

use App\Console\Commands\SyncAffiliationOrgFromRosterCommand;
use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncAffiliationOrgFromRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_updates_current_affiliation_org_fields(): void
    {
        $user = User::factory()->create([
            'name' => '西川 由希',
            'email' => 'sample@careearth.info',
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '旧部署',
            'section' => '旧課',
            'position' => '派遣',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,管轄,部署*,課/チーム*,役職【選択】,雇用形態,社用アドレス
西川 由希,Nishikawa Yuki,東京,管理本部,庶務課,リーダー,正社員,sample@careearth.info
CSV
        );

        Artisan::call(SyncAffiliationOrgFromRosterCommand::class, [
            'file' => $path,
        ]);

        $affiliation = $user->fresh()->currentAffiliation();
        $this->assertSame('東京', $affiliation?->location);
        $this->assertSame('管理本部', $affiliation?->department);
        $this->assertSame('庶務課', $affiliation?->section);
        $this->assertSame('リーダー', $affiliation?->position);
        $this->assertSame('CareEarth', $affiliation?->company);

        $detail = $user->fresh()->hrDetail;
        $this->assertSame('管理本部', $detail?->department_primary);
        $this->assertSame('庶務課', $detail?->section_primary);
    }

    public function test_command_does_not_create_affiliation_for_resigned_user(): void
    {
        $user = User::factory()->create([
            'name' => '退職 太郎',
            'email' => 'resigned@careearth.info',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_status' => '退職',
            'resigned_at' => '2024-08-31',
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-06-01',
            'end_date' => '2024-08-31',
            'enrollment_status' => AffiliationHistory::STATUS_RESIGNED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '不動産事業部',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,管轄,部署*,課/チーム*,役職【選択】,雇用形態,社用アドレス
退職 太郎,Resigned Taro,大阪,不動産事業部,,,正社員,resigned@careearth.info
CSV
        );

        Artisan::call(SyncAffiliationOrgFromRosterCommand::class, [
            'file' => $path,
        ]);

        $this->assertNull($user->fresh()->currentAffiliation());
        $this->assertSame(1, $user->affiliationHistories()->count());
        $this->assertSame(
            AffiliationHistory::STATUS_RESIGNED,
            $user->affiliationHistories()->first()?->enrollment_status,
        );
    }

    public function test_command_creates_affiliation_for_active_user_without_current_affiliation(): void
    {
        $user = User::factory()->create([
            'name' => '在籍 花子',
            'email' => 'active@careearth.info',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_status' => '在籍',
            'affiliation_code' => 'CE',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,管轄,部署*,課/チーム*,役職【選択】,雇用形態,社用アドレス
在籍 花子,Active Hanako,大阪,不動産事業部,,,正社員,active@careearth.info
CSV
        );

        Artisan::call(SyncAffiliationOrgFromRosterCommand::class, [
            'file' => $path,
        ]);

        $affiliation = $user->fresh()->currentAffiliation();
        $this->assertNotNull($affiliation);
        $this->assertSame(AffiliationHistory::STATUS_ENROLLED, $affiliation->enrollment_status);
        $this->assertSame('CareEarth', $affiliation->company);
        $this->assertSame('大阪', $affiliation->location);
        $this->assertSame('不動産事業部', $affiliation->department);
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
