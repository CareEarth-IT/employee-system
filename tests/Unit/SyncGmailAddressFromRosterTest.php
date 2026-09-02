<?php

namespace Tests\Unit;

use App\Console\Commands\SyncGmailAddressFromRosterCommand;
use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncGmailAddressFromRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_updates_gmail_address_only(): void
    {
        $user = User::factory()->create([
            'name' => '中村 佳史',
            'email' => 'yoshifumi_nakamura@careearth.info',
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => '役員',
            'position' => '代表',
            'location' => '大阪',
        ]);

        $detail = EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_status' => '在籍',
            'employment_type' => '役員',
            'company_phone' => '080-0000-0000',
            'gmail_address' => null,
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,Googleアドレス,社用アドレス
中村 佳史,Nakamura Yoshifumi,careearth.ny1@gmail.com,yoshifumi_nakamura@careearth.info
CSV
        );

        Artisan::call(SyncGmailAddressFromRosterCommand::class, [
            'file' => $path,
        ]);

        $detail->refresh();
        $this->assertSame('careearth.ny1@gmail.com', $detail->gmail_address);
        $this->assertSame('080-0000-0000', $detail->company_phone);
        $this->assertSame('在籍', $detail->employment_status);
        $this->assertSame('役員', $detail->employment_type);
        $this->assertSame('役員', $user->affiliationHistories()->first()->department);
    }

    public function test_command_creates_hr_detail_when_missing(): void
    {
        $user = User::factory()->create([
            'name' => 'ホサイン サジャード',
            'email' => 'hossain_sajjad@careearth.info',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,Googleアドレス,社用アドレス
ホサイン サジャード,Hossain Sajjad,hossainsajjad1.ce@gmail.com,hossain_sajjad@careearth.info
CSV
        );

        Artisan::call(SyncGmailAddressFromRosterCommand::class, [
            'file' => $path,
        ]);

        $detail = EmployeeHrDetail::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($detail);
        $this->assertSame('hossainsajjad1.ce@gmail.com', $detail->gmail_address);
    }

    public function test_command_skips_rows_without_google_address_in_csv(): void
    {
        $user = User::factory()->create([
            'name' => '石橋 愛士',
            'email' => 'aiji_ishibashi@careearth.info',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'gmail_address' => 'existing@gmail.com',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,Googleアドレス,社用アドレス
石橋 愛士,Ishibashi Aiji,,aiji_ishibashi@careearth.info
CSV
        );

        Artisan::call(SyncGmailAddressFromRosterCommand::class, [
            'file' => $path,
        ]);

        $detail = EmployeeHrDetail::query()->where('user_id', $user->id)->first();
        $this->assertSame('existing@gmail.com', $detail->gmail_address);
        $this->assertStringContainsString('Googleアドレス付きの行がありません', Artisan::output());
    }

    public function test_command_leaves_unchanged_when_gmail_already_matches(): void
    {
        $user = User::factory()->create([
            'name' => 'テスト 太郎',
            'email' => 'sample@careearth.info',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'gmail_address' => 'sample@gmail.com',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,Googleアドレス,社用アドレス
テスト 太郎,Test Taro,sample@gmail.com,sample@careearth.info
CSV
        );

        Artisan::call(SyncGmailAddressFromRosterCommand::class, [
            'file' => $path,
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('変更なし 1 件', Artisan::output());
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
