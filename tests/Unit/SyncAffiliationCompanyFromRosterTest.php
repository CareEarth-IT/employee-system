<?php

namespace Tests\Unit;

use App\Console\Commands\SyncAffiliationCompanyFromRosterCommand;
use App\Models\AffiliationHistory;
use App\Models\User;
use App\Support\EmployeeRosterCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncAffiliationCompanyFromRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_affiliation_code_to_company(): void
    {
        $this->assertSame('CareEarth', EmployeeRosterCsv::mapAffiliationCodeToCompany('CE'));
        $this->assertSame('GROWTEC', EmployeeRosterCsv::mapAffiliationCodeToCompany('GT'));
        $this->assertSame('Earth Management', EmployeeRosterCsv::mapAffiliationCodeToCompany('EM'));
        $this->assertSame('MidEarth', EmployeeRosterCsv::mapAffiliationCodeToCompany('MD'));
        $this->assertSame('MidEarth', EmployeeRosterCsv::mapAffiliationCodeToCompany('ME'));
        $this->assertNull(EmployeeRosterCsv::mapAffiliationCodeToCompany('XX'));
    }

    public function test_command_updates_bulk_import_affiliation_company(): void
    {
        $user = User::factory()->create([
            'name' => '中谷 亮介',
            'email' => 'ryosuke_nakatani@careearth.info',
        ]);

        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => SyncAffiliationCompanyFromRosterCommand::BULK_IMPORT_START_DATE,
            'enrollment_status' => AffiliationHistory::STATUS_MOVED,
            'end_date' => '2026-06-30',
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '旧部署',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,所属,社用アドレス
中谷 亮介,Nakatani Ryosuke,EM,ryosuke_nakatani@careearth.info
CSV
        );

        Artisan::call(SyncAffiliationCompanyFromRosterCommand::class, [
            'file' => $path,
        ]);

        $affiliation->refresh();
        $this->assertSame('Earth Management', $affiliation->company);
        $this->assertSame('旧部署', $affiliation->department);
    }

    public function test_command_does_not_change_manual_correction_affiliation(): void
    {
        $user = User::factory()->create([
            'name' => '中谷 亮介',
            'email' => 'ryosuke_nakatani@careearth.info',
        ]);

        $oldAffiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => SyncAffiliationCompanyFromRosterCommand::BULK_IMPORT_START_DATE,
            'end_date' => '2026-06-30',
            'enrollment_status' => AffiliationHistory::STATUS_MOVED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '旧部署',
        ]);

        $currentAffiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => SyncAffiliationCompanyFromRosterCommand::MANUAL_CORRECTION_START_DATE,
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => 'M&A戦略推進部',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,所属,社用アドレス
中谷 亮介,Nakatani Ryosuke,EM,ryosuke_nakatani@careearth.info
CSV
        );

        Artisan::call(SyncAffiliationCompanyFromRosterCommand::class, [
            'file' => $path,
        ]);

        $this->assertSame('Earth Management', $oldAffiliation->fresh()->company);
        $this->assertSame('CareEarth', $currentAffiliation->fresh()->company);
        $this->assertSame('M&A戦略推進部', $currentAffiliation->fresh()->department);
    }

    public function test_command_updates_import_locked_bulk_import_affiliation(): void
    {
        $user = User::factory()->create([
            'email' => 'sample@careearth.info',
        ]);

        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => SyncAffiliationCompanyFromRosterCommand::BULK_IMPORT_START_DATE,
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'import_locked' => true,
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,所属,社用アドレス
テスト 太郎,Test Taro,GT,sample@careearth.info
CSV
        );

        Artisan::call(SyncAffiliationCompanyFromRosterCommand::class, [
            'file' => $path,
            '--match-email-only' => true,
        ]);

        $affiliation->refresh();
        $this->assertSame('GROWTEC', $affiliation->company);
        $this->assertTrue($affiliation->import_locked);
    }

    public function test_command_updates_single_affiliation_company(): void
    {
        $user = User::factory()->create([
            'name' => '石橋 愛士',
            'email' => 'aiji_ishibashi@careearth.info',
        ]);

        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2026-05-29',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '不動産事業部',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,所属,社用アドレス
石橋 愛士,Ishibashi Aiji,GT,aiji_ishibashi@careearth.info
CSV
        );

        Artisan::call(SyncAffiliationCompanyFromRosterCommand::class, [
            'file' => $path,
        ]);

        $this->assertSame('GROWTEC', $affiliation->fresh()->company);
        $this->assertSame('不動産事業部', $affiliation->fresh()->department);
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
