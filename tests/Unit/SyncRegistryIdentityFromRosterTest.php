<?php

namespace Tests\Unit;

use App\Console\Commands\SyncAffiliationOrgFromRosterCommand;
use App\Console\Commands\SyncRegistryIdentityFromRosterCommand;
use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncRegistryIdentityFromRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_updates_identity_fields(): void
    {
        $user = User::factory()->create([
            'name' => '旧 名前',
            'email' => 'sample@careearth.info',
            'employee_id' => '10001',
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
            'name_kana' => '旧カナ',
            'english_name' => 'Old Name',
            'joined_at' => '2024-01-01',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'gender' => '男性',
            'remarks' => '旧備考',
            'jurisdiction' => '大阪',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,短縮表示,ナマエ,ID,性別,国籍,備考,管轄,社用アドレス
西川 由希,Nishikawa Yuki,西川,ニシカワユキ,12345,女性,日本,新備考,東京,sample@careearth.info
CSV
        );

        Artisan::call(SyncRegistryIdentityFromRosterCommand::class, [
            'file' => $path,
            '--match-email-only' => true,
        ]);

        $user->refresh();
        $profile = $user->profile;
        $detail = $user->hrDetail;

        $this->assertSame('西川 由希', $user->name);
        $this->assertSame('12345', $user->employee_id);
        $this->assertSame('ニシカワユキ', $profile?->name_kana);
        $this->assertSame('Nishikawa Yuki', $profile?->english_name);
        $this->assertSame('西川', $profile?->abbreviated_name);
        $this->assertSame('日本', $profile?->nationality);
        $this->assertSame('女性', $detail?->gender);
        $this->assertSame('新備考', $detail?->remarks);
        $this->assertSame('東京', $detail?->jurisdiction);
        $this->assertSame('ニシカワユキ', $detail?->name_kana_fullwidth);
        $this->assertSame('2024-01-01', $profile?->joined_at?->toDateString());
    }

    public function test_command_maps_nationality_code_from_csv(): void
    {
        $user = User::factory()->create([
            'email' => 'sample@careearth.info',
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,国籍,社用アドレス
西川 由希,VN,sample@careearth.info
CSV
        );

        Artisan::call(SyncRegistryIdentityFromRosterCommand::class, [
            'file' => $path,
            '--match-email-only' => true,
        ]);

        $this->assertSame('ベトナム', $user->fresh()->profile?->nationality);
    }

    public function test_command_truncates_long_abbreviated_name_from_csv(): void
    {
        $user = User::factory()->create([
            'email' => 'sample@careearth.info',
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,短縮表示,社用アドレス
テスト 太郎,Adhikari Milan,Adhikari Milan,sample@careearth.info
CSV
        );

        Artisan::call(SyncRegistryIdentityFromRosterCommand::class, [
            'file' => $path,
            '--match-email-only' => true,
        ]);

        $this->assertSame('Adhikari M', $user->fresh()->profile?->abbreviated_name);
    }

    public function test_command_skips_duplicate_employee_id(): void
    {
        User::factory()->create([
            'email' => 'other@careearth.info',
            'employee_id' => '12345',
        ]);

        $user = User::factory()->create([
            'name' => '西川 由希',
            'email' => 'sample@careearth.info',
            'employee_id' => '10001',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,ID,社用アドレス
西川 由希,Nishikawa Yuki,12345,sample@careearth.info
CSV
        );

        Artisan::call(SyncRegistryIdentityFromRosterCommand::class, [
            'file' => $path,
        ]);

        $this->assertSame('10001', $user->fresh()->employee_id);
        $this->assertStringContainsString('社員ID重複', Artisan::output());
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
