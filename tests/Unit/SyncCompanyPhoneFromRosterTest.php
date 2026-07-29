<?php

namespace Tests\Unit;

use App\Console\Commands\SyncCompanyPhoneFromRosterCommand;
use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncCompanyPhoneFromRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_updates_company_phone_only(): void
    {
        $user = User::factory()->create([
            'name' => '中谷 亮介',
            'email' => 'ryosuke_nakatani@careearth.info',
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'Earth Management',
            'department' => 'M&A戦略推進部',
            'position' => '正社員',
            'location' => '大阪',
        ]);

        $detail = EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_status' => '在籍',
            'employment_type' => '正社員',
            'phone' => '080-0000-0000',
            'company_phone' => null,
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,電話番号,社用アドレス
中谷 亮介,Nakatani Ryosuke,080-4134-7128,ryosuke_nakatani@careearth.info
CSV
        );

        Artisan::call(SyncCompanyPhoneFromRosterCommand::class, [
            'file' => $path,
        ]);

        $detail->refresh();
        $this->assertSame('080-4134-7128', $detail->company_phone);
        $this->assertSame('080-0000-0000', $detail->phone);
        $this->assertSame('在籍', $detail->employment_status);
        $this->assertSame('正社員', $detail->employment_type);
        $this->assertSame('M&A戦略推進部', $user->affiliationHistories()->first()->department);
    }

    public function test_command_creates_hr_detail_when_missing(): void
    {
        $user = User::factory()->create([
            'name' => '石橋 愛士',
            'email' => 'aiji_ishibashi@careearth.info',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,電話番号,社用アドレス
石橋 愛士,Ishibashi Aiji,080-4186-7038,aiji_ishibashi@careearth.info
CSV
        );

        Artisan::call(SyncCompanyPhoneFromRosterCommand::class, [
            'file' => $path,
        ]);

        $detail = EmployeeHrDetail::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($detail);
        $this->assertSame('080-4186-7038', $detail->company_phone);
    }

    public function test_command_skips_rows_without_phone_in_csv(): void
    {
        $user = User::factory()->create([
            'name' => 'テスト 太郎',
            'email' => 'sample@careearth.info',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'company_phone' => '080-1111-2222',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,電話番号,社用アドレス
テスト 太郎,Test Taro,,sample@careearth.info
CSV
        );

        Artisan::call(SyncCompanyPhoneFromRosterCommand::class, [
            'file' => $path,
        ]);

        $detail = EmployeeHrDetail::query()->where('user_id', $user->id)->first();
        $this->assertSame('080-1111-2222', $detail->company_phone);
        $this->assertStringContainsString('電話番号付きの行がありません', Artisan::output());
    }

    public function test_command_leaves_unchanged_when_phone_already_matches(): void
    {
        $user = User::factory()->create([
            'name' => 'テスト 太郎',
            'email' => 'sample@careearth.info',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'company_phone' => '080-3333-4444',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,電話番号,社用アドレス
テスト 太郎,Test Taro,080-3333-4444,sample@careearth.info
CSV
        );

        Artisan::call(SyncCompanyPhoneFromRosterCommand::class, [
            'file' => $path,
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('変更なし 1 件', Artisan::output());
    }

    public function test_command_stores_all_phones_when_csv_has_multiple(): void
    {
        $user = User::factory()->create([
            'name' => 'テスト 太郎',
            'email' => 'sample@careearth.info',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,電話番号,社用アドレス
テスト 太郎,Test Taro,"080-1111-2222, 080-3333-4444",sample@careearth.info
CSV
        );

        Artisan::call(SyncCompanyPhoneFromRosterCommand::class, [
            'file' => $path,
        ]);

        $detail = EmployeeHrDetail::query()->where('user_id', $user->id)->first();
        $this->assertSame('080-1111-2222, 080-3333-4444', $detail->company_phone);
        $this->assertSame(['080-1111-2222', '080-3333-4444'], $detail->companyPhoneList());
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
