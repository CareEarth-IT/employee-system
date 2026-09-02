<?php

namespace Tests\Unit;

use App\Support\RegistryOrgAssignment;
use App\Support\RegistrySectionOptions;
use App\Support\RegistryTeamOptions;
use PHPUnit\Framework\TestCase;

class RegistryOrgAssignmentTest extends TestCase
{
    public function test_split_returns_section_and_team_from_combined_value(): void
    {
        $result = RegistryOrgAssignment::split('Food Logistics部,出荷チーム');

        $this->assertSame('Food Logistics部', $result['section']);
        $this->assertSame('出荷チーム', $result['team']);
    }

    public function test_split_returns_team_only_value(): void
    {
        $result = RegistryOrgAssignment::split('出荷チーム');

        $this->assertNull($result['section']);
        $this->assertSame('出荷チーム', $result['team']);
    }

    public function test_combine_joins_section_and_team(): void
    {
        $this->assertSame(
            'Food Logistics部,出荷チーム',
            RegistryOrgAssignment::combine('Food Logistics部', '出荷チーム'),
        );
    }

    public function test_section_and_team_options_do_not_overlap(): void
    {
        $overlap = array_intersect(RegistrySectionOptions::options(), RegistryTeamOptions::options());

        $this->assertSame([], array_values($overlap));
    }

    public function test_hr_detail_primary_from_food_sales_team_only_affiliation(): void
    {
        $primary = RegistryOrgAssignment::hrDetailPrimaryFromAffiliation(
            'Food Sales部',
            '大阪',
            '法人チーム',
        );

        $this->assertSame('Food Sales部', $primary['department_primary']);
        $this->assertSame('法人チーム', $primary['section_primary']);
    }

    public function test_hr_detail_primary_from_gr_affiliation(): void
    {
        $primary = RegistryOrgAssignment::hrDetailPrimaryFromAffiliation(
            'GR部（グローバル部）',
            '大阪',
            'GR-O_大阪,GR-O CS課 固定現場チーム_大阪',
        );

        $this->assertSame('GR部（グローバル部）', $primary['department_primary']);
        $this->assertSame('GR-O_大阪', $primary['section_primary']);
    }
}
