<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ImportMissingFromRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_missing_resigned_employee_with_short_roster_id(): void
    {
        $path = $this->writeRosterCsv([
            [
                '名前' => '原見 優來',
                'Name' => 'Harami Yurai',
                '短縮表示' => '原見',
                '状況' => '退職',
                '所属' => 'GT',
                'ID' => '29',
                '雇用形態' => '正社員',
                '管轄' => '大阪',
                '国籍' => 'JP',
                '性別' => '男',
                '社用アドレス' => 'yurai_test_import@careearth.info',
                '入社予定日' => '2024/2/1',
                '入社日' => '2/1/2024',
            ],
            [
                '名前' => '既存 太郎',
                'Name' => 'Existing Taro',
                '短縮表示' => '既存',
                '状況' => '退職',
                '所属' => 'CE',
                'ID' => '10001',
                '雇用形態' => '正社員',
                '管轄' => '大阪',
                '国籍' => 'JP',
                '性別' => '男',
                '社用アドレス' => 'existing_roster@careearth.info',
                '入社予定日' => '2024/1/1',
                '入社日' => '1/1/2024',
            ],
        ]);

        User::factory()->create([
            'email' => 'existing_roster@careearth.info',
            'employee_id' => '10001',
        ]);

        $exit = Artisan::call('employee:import-missing-from-roster', [
            'file' => $path,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);

        $created = User::query()->where('email', 'yurai_test_import@careearth.info')->first();
        $this->assertNotNull($created, $output);
        $this->assertSame('29', $created->employee_id);
        $this->assertSame('退職', $created->hrDetail?->employment_status);
        $this->assertSame('2024-02-01', $created->profile?->joined_at?->format('Y-m-d'));
        $this->assertTrue($created->isListedEmployee());

        $this->assertSame(1, User::query()->where('email', 'existing_roster@careearth.info')->count());

        @unlink($path);
    }

    public function test_imports_resigned_employee_without_company_email(): void
    {
        $path = $this->writeRosterCsv([[
            '名前' => '星名 諒',
            'Name' => 'Hoshina Ryo',
            '短縮表示' => '星名',
            '状況' => '退職',
            '所属' => 'CE',
            'ID' => '3',
            '雇用形態' => '正社員',
            '管轄' => '大阪',
            '国籍' => 'JP',
            '性別' => '男',
            '社用アドレス' => '―',
            '入社予定日' => '2023/4/1',
            '入社日' => '4/1/2023',
            '退職日' => '3/31/2024',
        ]]);

        $exit = Artisan::call('employee:import-missing-from-roster', [
            'file' => $path,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);

        $created = User::query()->where('employee_id', '3')->first();
        $this->assertNotNull($created, $output);
        $this->assertNull($created->email);
        $this->assertSame('退職', $created->hrDetail?->employment_status);
        $this->assertTrue($created->isListedEmployee());

        @unlink($path);
    }

    public function test_dry_run_does_not_create_users(): void
    {
        $path = $this->writeRosterCsv([[
            '名前' => '名簿 花子',
            'Name' => 'Roster Hanako',
            '短縮表示' => '名簿',
            '状況' => '退職',
            '所属' => 'CE',
            'ID' => '53',
            '雇用形態' => '正社員',
            '管轄' => '東京',
            '国籍' => 'JP',
            '性別' => '女',
            '社用アドレス' => 'roster_dry_run@careearth.info',
            '入社予定日' => '2024/6/1',
            '入社日' => '6/1/2024',
            '退職日' => '8/31/2024',
        ]]);

        $exit = Artisan::call('employee:import-missing-from-roster', [
            'file' => $path,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertNull(User::query()->where('email', 'roster_dry_run@careearth.info')->first());

        @unlink($path);
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function writeRosterCsv(array $rows): string
    {
        $headers = [
            '名前', 'Name', '短縮表示', '状況', '所属', 'ID', '雇用形態', '部署*', '課/チーム*',
            '役職【選択】', '役職補足＆説明', '役職【表示】', '管轄', '国籍', '性別', '生年月日',
            '電話番号', '社用アドレス', 'Googleアドレス', 'Facebook', 'デバイス設定',
            '入社予定日', '入社日', '退職日', '備考',
        ];

        $path = storage_path('app/roster-import-'.uniqid('', true).'.csv');
        $handle = fopen($path, 'wb');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }
            fputcsv($handle, $line);
        }

        fclose($handle);

        return $path;
    }
}
