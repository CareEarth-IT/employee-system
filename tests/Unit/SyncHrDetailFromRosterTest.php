<?php

namespace Tests\Unit;

use App\Console\Commands\SyncHrDetailFromRosterCommand;
use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\EquipmentPurchaseApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncHrDetailFromRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_updates_status_and_employment_type_only(): void
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
            'employment_status' => '在籍中',
            'employment_type' => null,
            'phone' => '080-0000-0000',
            'department_primary' => '手入力部署',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,状況,雇用形態,社用アドレス
中谷 亮介,Nakatani Ryosuke,在籍,正社員,ryosuke_nakatani@careearth.info
CSV
        );

        Artisan::call(SyncHrDetailFromRosterCommand::class, [
            'file' => $path,
        ]);

        $detail->refresh();
        $this->assertSame('在籍', $detail->employment_status);
        $this->assertSame('正社員', $detail->employment_type);
        $this->assertSame('080-0000-0000', $detail->phone);
        $this->assertSame('手入力部署', $detail->department_primary);
        $this->assertSame('M&A戦略推進部', $user->affiliationHistories()->first()->department);
    }

    public function test_command_creates_hr_detail_when_missing(): void
    {
        $user = User::factory()->create([
            'name' => '石橋 愛士',
            'email' => 'aiji_ishibashi@careearth.info',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,状況,雇用形態,社用アドレス
石橋 愛士,Ishibashi Aiji,在籍,正社員,aiji_ishibashi@careearth.info
CSV
        );

        Artisan::call(SyncHrDetailFromRosterCommand::class, [
            'file' => $path,
        ]);

        $detail = EmployeeHrDetail::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($detail);
        $this->assertSame('在籍', $detail->employment_status);
        $this->assertSame('正社員', $detail->employment_type);
    }

    public function test_command_skips_empty_csv_fields_without_clearing_db(): void
    {
        $user = User::factory()->create([
            'name' => 'テスト 太郎',
            'email' => 'sample@careearth.info',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_status' => '退職',
            'employment_type' => '正社員',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,状況,雇用形態,社用アドレス
テスト 太郎,Test Taro,在籍,,sample@careearth.info
CSV
        );

        Artisan::call(SyncHrDetailFromRosterCommand::class, [
            'file' => $path,
        ]);

        $detail = EmployeeHrDetail::query()->where('user_id', $user->id)->first();
        $this->assertSame('在籍', $detail->employment_status);
        $this->assertSame('正社員', $detail->employment_type);
    }

    public function test_command_leaves_unchanged_when_values_already_match(): void
    {
        $user = User::factory()->create([
            'name' => 'テスト 太郎',
            'email' => 'sample@careearth.info',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_status' => '在籍',
            'employment_type' => '正社員',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,状況,雇用形態,社用アドレス
テスト 太郎,Test Taro,在籍,正社員,sample@careearth.info
CSV
        );

        Artisan::call(SyncHrDetailFromRosterCommand::class, [
            'file' => $path,
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('変更なし 1 件', Artisan::output());
    }

    public function test_command_does_not_change_equipment_purchase_applications(): void
    {
        $user = User::factory()->create([
            'name' => '中谷 亮介',
            'email' => 'ryosuke_nakatani@careearth.info',
        ]);

        $application = EquipmentPurchaseApplication::create([
            'user_id' => $user->id,
            'application_type' => EquipmentPurchaseApplication::TYPE_INTERNAL_UNDER_30K,
            'application_date' => '2026-01-15',
            'purchase_site' => 'amazon',
            'purchase_site_url' => 'https://example.com/item',
            'product_name' => 'テスト備品',
            'quantity' => 1,
            'price_including_tax' => 10000,
            'purchase_reason' => '業務利用',
            'item_destination' => 'office',
            'delivery_destination' => 'office',
            'purchase_urgency' => 'normal',
            'status' => EquipmentPurchaseApplication::STATUS_APPROVED,
            'remarks' => '承認済みの備品申請',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,状況,雇用形態,社用アドレス
中谷 亮介,Nakatani Ryosuke,在籍,正社員,ryosuke_nakatani@careearth.info
CSV
        );

        Artisan::call(SyncHrDetailFromRosterCommand::class, [
            'file' => $path,
        ]);

        $application->refresh();
        $this->assertSame(EquipmentPurchaseApplication::STATUS_APPROVED, $application->status);
        $this->assertSame('テスト備品', $application->product_name);
        $this->assertSame('承認済みの備品申請', $application->remarks);
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
