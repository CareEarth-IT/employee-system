<?php

namespace Tests\Unit;

use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\HrDetailOrgFormState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrDetailOrgFormStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_values_split_stored_section_and_team(): void
    {
        $user = User::factory()->create();
        $detail = EmployeeHrDetail::create([
            'user_id' => $user->id,
            'department_primary' => 'GR部（グローバル部）',
            'section_primary' => 'GR-O_大阪,GR-O CS課 固定現場チーム_大阪',
            'jurisdiction' => '大阪',
        ]);

        $values = HrDetailOrgFormState::assignmentValues($detail, 'primary');

        $this->assertSame('GR部（グローバル部）', $values['department']);
        $this->assertSame('大阪', $values['location']);
        $this->assertSame('GR-O部', $values['section']);
        $this->assertSame('固定現場チーム', $values['team']);
    }

    public function test_merge_section_fields_stores_gr_o_nested_team_in_section_primary(): void
    {
        $merged = HrDetailOrgFormState::mergeSectionFields([
            'department_primary' => 'GR部（グローバル部）',
            'section_primary' => 'GR-O部',
            'team_primary' => '固定現場チーム',
            'jurisdiction' => '大阪',
        ]);

        $this->assertSame('GR部（グローバル部）', $merged['department_primary']);
        $this->assertSame(
            'GR-O_大阪,GR-O CS課 固定現場チーム_大阪',
            $merged['section_primary'],
        );
        $this->assertArrayNotHasKey('team_primary', $merged);
    }

    public function test_merge_section_fields_stores_food_department_section_and_team_in_section_primary(): void
    {
        $merged = HrDetailOrgFormState::mergeSectionFields([
            'department_primary' => '食品事業部',
            'section_primary' => 'Food Sales部',
            'team_primary' => '法人チーム',
            'jurisdiction' => '大阪',
        ]);

        $this->assertSame('食品事業部', $merged['department_primary']);
        $this->assertSame('Food Sales部,法人チーム', $merged['section_primary']);
        $this->assertArrayNotHasKey('team_primary', $merged);
    }

    public function test_merge_section_fields_stores_legacy_food_sales_team_in_section_primary(): void
    {
        $merged = HrDetailOrgFormState::mergeSectionFields([
            'department_primary' => 'Food Sales部',
            'section_primary' => '',
            'team_primary' => '法人チーム',
            'jurisdiction' => '大阪',
        ]);

        $this->assertSame('法人チーム', $merged['section_primary']);
        $this->assertArrayNotHasKey('team_primary', $merged);
    }
}
