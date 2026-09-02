<?php

namespace Tests\Unit;

use App\Support\EmployeeRosterCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirtableRosterCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_airtable_csv_headers_parse_without_legacy_columns(): void
    {
        $path = $this->writeRosterCsv(<<<'CSV'
社員番号,氏名,短縮表示,メールアドレス,拠点,部署,課,役職,社員種別,生年月日,性別,入社日,備考
12345,西川 由希,西川,yuki@careearth.info,東京,管理本部,庶務課,一般,正社員,1990/4/1,女性,2023/11/6,テスト備考
CSV
        );

        $identityRows = EmployeeRosterCsv::readRegistryIdentityRows($path);
        $this->assertCount(1, $identityRows);
        $this->assertSame('12345', $identityRows[0]['employee_id']);
        $this->assertSame('西川 由希', $identityRows[0]['name']);
        $this->assertSame('西川', $identityRows[0]['abbreviated_name']);
        $this->assertSame('yuki@careearth.info', $identityRows[0]['email']);
        $this->assertSame('東京', $identityRows[0]['jurisdiction']);
        $this->assertSame('女性', $identityRows[0]['gender']);
        $this->assertSame('1990-04-01', $identityRows[0]['birth_date']);
        $this->assertSame('テスト備考', $identityRows[0]['remarks']);

        $joinedRows = EmployeeRosterCsv::readRows($path);
        $this->assertCount(1, $joinedRows);
        $this->assertSame('2023-11-06', $joinedRows[0]['joined_at']);

        $hrRows = EmployeeRosterCsv::readHrDetailRows($path);
        $this->assertCount(1, $hrRows);
        $this->assertSame('正社員', $hrRows[0]['employment_type']);
        $this->assertSame('', $hrRows[0]['employment_status']);

        $orgRows = EmployeeRosterCsv::readHrDetailOrgPrimaryRows($path);
        $this->assertCount(1, $orgRows);
        $this->assertSame('管理本部', $orgRows[0]['department_primary']);
        $this->assertSame('庶務課', $orgRows[0]['section_primary']);
        $this->assertSame('一般', $orgRows[0]['position_primary']);
        $this->assertSame('', $orgRows[0]['affiliation_code']);

        $affiliationOrgRows = EmployeeRosterCsv::readAffiliationOrgRows($path);
        $this->assertCount(1, $affiliationOrgRows);
        $this->assertSame('東京', $affiliationOrgRows[0]['location']);
        $this->assertSame('正社員', $affiliationOrgRows[0]['employment_type']);

        $companyRows = EmployeeRosterCsv::readAffiliationRows($path);
        $this->assertSame([], $companyRows);

        $phoneRows = EmployeeRosterCsv::readCompanyPhoneRows($path);
        $this->assertSame([], $phoneRows);
    }

    public function test_joined_at_prefers_hire_date_over_planned_date(): void
    {
        $path = $this->writeRosterCsv(<<<'CSV'
氏名,メールアドレス,入社日,入社予定日
西川 由希,yuki@careearth.info,2023/11/6,2024/1/1
CSV
        );

        $rows = EmployeeRosterCsv::readRows($path);

        $this->assertCount(1, $rows);
        $this->assertSame('2023-11-06', $rows[0]['joined_at']);
    }

    public function test_planned_joined_at_used_when_hire_date_empty(): void
    {
        $path = $this->writeRosterCsv(<<<'CSV'
氏名,メールアドレス,入社予定日
西川 由希,yuki@careearth.info,2024/3/11
CSV
        );

        $rows = EmployeeRosterCsv::readRows($path);

        $this->assertCount(1, $rows);
        $this->assertSame('2024-03-11', $rows[0]['joined_at']);
    }

    public function test_affiliation_code_maps_to_portal_company_name(): void
    {
        $path = $this->writeRosterCsv(<<<'CSV'
氏名,メールアドレス,所属
テスト CE,ce@careearth.info,CE
テスト ME,me@careearth.info,ME
テスト GT,gt@careearth.info,GT
テスト EM,em@careearth.info,EM
テスト CEVN,cevn@careearth.info,CEVN
CSV
        );

        $rows = EmployeeRosterCsv::readAffiliationRows($path);

        $this->assertSame([
            'CareEarth',
            'MidEarth',
            'GROWTEC',
            'Earth Management',
            'Care EarthVietnam',
        ], array_column($rows, 'company'));
    }

    public function test_roster_csv_maps_all_nationality_codes_to_display_names(): void
    {
        $path = $this->writeRosterCsv(<<<'CSV'
氏名,国籍,社用アドレス
A,BD,a@careearth.info
B,ID,b@careearth.info
C,JP,c@careearth.info
D,LK,d@careearth.info
E,MM,e@careearth.info
F,NP,f@careearth.info
G,TW,g@careearth.info
H,VN,h@careearth.info
I,KR,i@careearth.info
J,IN,j@careearth.info
K,CN,k@careearth.info
CSV
        );

        $rows = EmployeeRosterCsv::readRegistryIdentityRows($path);

        $this->assertSame([
            'バングラデシュ',
            'インドネシア',
            '日本',
            'スリランカ',
            'ミャンマー',
            'ネパール',
            '台湾',
            'ベトナム',
            '韓国',
            'インド',
            '中国',
        ], array_column($rows, 'nationality'));
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
