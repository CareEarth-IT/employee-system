<?php

namespace Tests\Unit;

use App\Console\Commands\SyncHrDetailOrgPrimaryFromRosterCommand;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncHrDetailOrgPrimaryFromRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_updates_org_primary_fields_only(): void
    {
        $user = User::factory()->create([
            'name' => '西川 由希',
            'email' => 'yuki_nishikawa@careearth.info',
        ]);

        $detail = EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_status' => '在籍',
            'employment_type' => '正社員',
            'company_phone' => '080-1111-2222',
            'affiliation_code' => null,
            'department_primary' => null,
            'section_primary' => null,
            'position_primary' => null,
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,所属,部署*,課/チーム*,役職【選択】,社用アドレス
西川 由希,Nishikawa Yuki,CE,管理本部,庶務課,一般,yuki_nishikawa@careearth.info
CSV
        );

        Artisan::call(SyncHrDetailOrgPrimaryFromRosterCommand::class, [
            'file' => $path,
        ]);

        $detail->refresh();
        $this->assertSame('CE', $detail->affiliation_code);
        $this->assertSame('管理本部', $detail->department_primary);
        $this->assertSame('庶務課', $detail->section_primary);
        $this->assertSame('一般', $detail->position_primary);
        $this->assertSame('在籍', $detail->employment_status);
        $this->assertSame('正社員', $detail->employment_type);
        $this->assertSame('080-1111-2222', $detail->company_phone);
    }

    public function test_command_skips_empty_csv_fields_without_clearing_db(): void
    {
        $user = User::factory()->create([
            'name' => 'テスト 太郎',
            'email' => 'sample@careearth.info',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'affiliation_code' => 'EM',
            'department_primary' => '既存部署',
            'section_primary' => '既存課',
            'position_primary' => '既存役職',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,所属,部署*,課/チーム*,役職【選択】,社用アドレス
テスト 太郎,Test Taro,GT,食品事業部,,,sample@careearth.info
CSV
        );

        Artisan::call(SyncHrDetailOrgPrimaryFromRosterCommand::class, [
            'file' => $path,
        ]);

        $detail = EmployeeHrDetail::query()->where('user_id', $user->id)->first();
        $this->assertSame('GT', $detail->affiliation_code);
        $this->assertSame('食品事業部', $detail->department_primary);
        $this->assertSame('既存課', $detail->section_primary);
        $this->assertSame('既存役職', $detail->position_primary);
    }

    public function test_command_creates_hr_detail_when_missing(): void
    {
        $user = User::factory()->create([
            'name' => '中谷 亮介',
            'email' => 'ryosuke_nakatani@careearth.info',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,所属,部署*,課/チーム*,役職【選択】,社用アドレス
中谷 亮介,Nakatani Ryosuke,EM,M&A戦略推進部,,,ryosuke_nakatani@careearth.info
CSV
        );

        Artisan::call(SyncHrDetailOrgPrimaryFromRosterCommand::class, [
            'file' => $path,
        ]);

        $detail = EmployeeHrDetail::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($detail);
        $this->assertSame('EM', $detail->affiliation_code);
        $this->assertSame('M&A戦略推進部', $detail->department_primary);
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
