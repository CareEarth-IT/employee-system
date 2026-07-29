<?php

namespace Tests\Feature;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_shows_search_selects(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('状況', false)
            ->assertSee('所属会社', false)
            ->assertSee('社員ID', false)
            ->assertSee('雇用形態', false)
            ->assertSee('アドレス', false)
            ->assertSee('電話番号', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="company"', false)
            ->assertSee('name="employee_id"', false)
            ->assertSee('name="employment_type"', false)
            ->assertDontSee('name="location"', false)
            ->assertDontSee('name="position"', false)
            ->assertSee('CareEarth', false)
            ->assertSee('正社員', false)
            ->assertSee('アルバイト', false);
    }

    public function test_index_shows_company_email_and_company_phone_columns(): void
    {
        $viewer = User::factory()->create();

        $employee = User::factory()->create([
            'email' => 'sample_user@careearth.info',
            'last_name' => '山田',
            'first_name' => '太郎',
        ]);
        AffiliationHistory::create([
            'user_id' => $employee->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => '通信部',
        ]);
        EmployeeHrDetail::create([
            'user_id' => $employee->id,
            'company_phone' => '080-1234-5678',
        ]);

        $this->actingAs($viewer)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('sample_user@careearth.info', false)
            ->assertSee('080-1234-5678', false)
            ->assertSeeInOrder([
                'Name (ENG)',
                '名前 / カタカナ',
                'アドレス',
                '電話番号',
            ], false);
    }

    public function test_index_shows_multiple_company_phones_on_separate_lines(): void
    {
        $viewer = User::factory()->create();

        $employee = User::factory()->create([
            'email' => 'multi_phone@careearth.info',
            'last_name' => '複数',
            'first_name' => '電話',
        ]);
        AffiliationHistory::create([
            'user_id' => $employee->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
        ]);
        EmployeeHrDetail::create([
            'user_id' => $employee->id,
            'company_phone' => '080-1111-2222, 080-3333-4444',
        ]);

        $response = $this->actingAs($viewer)
            ->get(route('employees.index'))
            ->assertOk();

        $response->assertSee('080-1111-2222', false);
        $response->assertSee('080-3333-4444', false);
    }

    public function test_index_filters_by_company(): void
    {
        $viewer = User::factory()->create();

        $careEarthUser = User::factory()->create(['last_name' => 'ケア', 'first_name' => '太郎']);
        AffiliationHistory::create([
            'user_id' => $careEarthUser->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '通信部',
        ]);

        $growtecUser = User::factory()->create(['last_name' => 'グロ', 'first_name' => '花子']);
        AffiliationHistory::create([
            'user_id' => $growtecUser->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'GrowTEC',
            'location' => '東京',
            'department' => '営業部',
        ]);

        $this->actingAs($viewer)
            ->get(route('employees.index', ['company' => 'CareEarth']))
            ->assertOk()
            ->assertSee('ケア 太郎', false)
            ->assertDontSee('グロ 花子', false);
    }

    public function test_index_filters_by_employee_id(): void
    {
        $viewer = User::factory()->create();

        $matched = User::factory()->create([
            'last_name' => '社員',
            'first_name' => '一致',
            'employee_id' => '255',
        ]);
        AffiliationHistory::create([
            'user_id' => $matched->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'location' => '大阪',
        ]);

        $other = User::factory()->create([
            'last_name' => '別',
            'first_name' => '人',
            'employee_id' => '999',
        ]);
        AffiliationHistory::create([
            'user_id' => $other->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'location' => '大阪',
        ]);

        $this->actingAs($viewer)
            ->get(route('employees.index', ['employee_id' => '255']))
            ->assertOk()
            ->assertSee('社員 一致', false)
            ->assertDontSee('別 人', false);
    }

    public function test_index_filters_by_employment_type_from_hr_detail(): void
    {
        $viewer = User::factory()->create();

        $regular = User::factory()->create(['last_name' => '正社員', 'first_name' => '太郎']);
        AffiliationHistory::create([
            'user_id' => $regular->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'location' => '大阪',
            'position' => '代表',
        ]);
        EmployeeHrDetail::create([
            'user_id' => $regular->id,
            'employment_type' => '正社員',
        ]);

        $partTime = User::factory()->create(['last_name' => 'アルバイト', 'first_name' => '花子']);
        AffiliationHistory::create([
            'user_id' => $partTime->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'location' => '大阪',
            'position' => 'アルバイト',
        ]);

        $this->actingAs($viewer)
            ->get(route('employees.index', ['employment_type' => '正社員']))
            ->assertOk()
            ->assertSee('正社員 太郎', false)
            ->assertDontSee('アルバイト 花子', false);
    }

    public function test_index_filters_by_status_resigned(): void
    {
        $viewer = User::factory()->create();

        $active = User::factory()->create(['last_name' => '在籍', 'first_name' => '太郎']);
        AffiliationHistory::create([
            'user_id' => $active->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'location' => '大阪',
        ]);

        $resigned = User::factory()->create(['last_name' => '退職', 'first_name' => '花子']);
        AffiliationHistory::create([
            'user_id' => $resigned->id,
            'start_date' => '2020-01-01',
            'end_date' => '2023-12-31',
            'enrollment_status' => AffiliationHistory::STATUS_RESIGNED,
            'location' => '大阪',
        ]);

        $this->actingAs($viewer)
            ->get(route('employees.index', ['status' => '退職']))
            ->assertOk()
            ->assertSee('退職 花子', false)
            ->assertDontSee('在籍 太郎', false);
    }

    public function test_index_filters_by_status_declined(): void
    {
        $viewer = User::factory()->create();

        $declined = User::factory()->create(['last_name' => '辞退', 'first_name' => '太郎']);
        EmployeeHrDetail::create([
            'user_id' => $declined->id,
            'employment_status' => '辞退',
        ]);

        $active = User::factory()->create(['last_name' => '在籍', 'first_name' => '花子']);
        AffiliationHistory::create([
            'user_id' => $active->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'location' => '大阪',
        ]);

        $this->actingAs($viewer)
            ->get(route('employees.index', ['status' => '辞退']))
            ->assertOk()
            ->assertSee('辞退 太郎', false)
            ->assertDontSee('在籍 花子', false);
    }

    public function test_index_displays_status_company_and_employment_type_columns(): void
    {
        $viewer = User::factory()->create();

        $enrolled = User::factory()->create([
            'last_name' => '在籍',
            'first_name' => '太郎',
            'employee_id' => '10001',
        ]);
        AffiliationHistory::create([
            'user_id' => $enrolled->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'position' => '代表',
        ]);
        EmployeeHrDetail::create([
            'user_id' => $enrolled->id,
            'employment_status' => '在籍',
            'employment_type' => '正社員',
        ]);

        $resigned = User::factory()->create([
            'last_name' => '退職',
            'first_name' => '花子',
            'employee_id' => '10002',
        ]);
        AffiliationHistory::create([
            'user_id' => $resigned->id,
            'start_date' => '2020-01-01',
            'end_date' => '2023-12-31',
            'enrollment_status' => AffiliationHistory::STATUS_RESIGNED,
            'company' => 'GrowTEC',
            'location' => '東京',
            'position' => '正社員',
        ]);

        $this->actingAs($viewer)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('状況', false)
            ->assertSee('所属会社', false)
            ->assertSee('雇用形態', false)
            ->assertSee('CareEarth', false)
            ->assertSee('GrowTEC', false)
            ->assertSee('10001', false)
            ->assertSee('10002', false)
            ->assertSee('在籍', false)
            ->assertSee('退職', false)
            ->assertSee('正社員', false)
            ->assertDontSee('拠点', false)
            ->assertDontSee('役職', false);
    }

    public function test_index_sorts_by_employee_id_asc_and_desc(): void
    {
        $viewer = User::factory()->create(['employee_id' => '90000']);

        $low = User::factory()->create([
            'last_name' => '小',
            'first_name' => '番号',
            'employee_id' => '255',
        ]);
        $high = User::factory()->create([
            'last_name' => '大',
            'first_name' => '番号',
            'employee_id' => '10269',
        ]);
        $mid = User::factory()->create([
            'last_name' => '中',
            'first_name' => '番号',
            'employee_id' => '10042',
        ]);

        foreach ([$low, $high, $mid] as $employee) {
            AffiliationHistory::create([
                'user_id' => $employee->id,
                'start_date' => '2024-01-01',
                'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                'company' => 'CareEarth',
            ]);
        }

        $this->actingAs($viewer)
            ->get(route('employees.index', ['sort' => 'employee_id', 'direction' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['255', '10042', '10269'], false)
            ->assertSee('社員ID: 昇順', false);

        $this->actingAs($viewer)
            ->get(route('employees.index', ['sort' => 'employee_id', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['10269', '10042', '255'], false)
            ->assertSee('社員ID: 降順', false);
    }

    public function test_index_employee_id_header_toggles_sort_direction(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('sort=employee_id&amp;direction=asc', false);

        $this->actingAs($viewer)
            ->get(route('employees.index', ['sort' => 'employee_id', 'direction' => 'asc']))
            ->assertOk()
            ->assertSee('sort=employee_id&amp;direction=desc', false);
    }
}
