<?php

namespace Tests\Unit;

use App\Support\RegistryTeamByAssignment;
use PHPUnit\Framework\TestCase;

class RegistryTeamByAssignmentTest extends TestCase
{
    public function test_food_sales_teams_do_not_require_section(): void
    {
        $this->assertSame(
            ['法人チーム', 'ECチーム'],
            RegistryTeamByAssignment::optionsFor('Food Sales部', '', ''),
        );
        $this->assertTrue(RegistryTeamByAssignment::isDepartmentTeamOnly('Food Sales部'));
        $this->assertTrue(RegistryTeamByAssignment::shouldShowField('Food Sales部', '', ''));
        $this->assertFalse(RegistryTeamByAssignment::shouldShowField('経理部', '', ''));
        $this->assertFalse(RegistryTeamByAssignment::shouldShowField('GR部（グローバル部）', '大阪', ''));
        $this->assertTrue(RegistryTeamByAssignment::shouldShowField('GR部（グローバル部）', '大阪', 'GR-C部'));
    }

    public function test_food_logistic_teams(): void
    {
        $this->assertSame(
            ['運送チーム', '出荷チーム'],
            RegistryTeamByAssignment::optionsFor('Food Logistic部', '大阪', ''),
        );
    }

    public function test_gr_c_osaka_teams(): void
    {
        $this->assertSame(
            ['総務課', '教育課'],
            RegistryTeamByAssignment::optionsFor('GR部（グローバル部）', '大阪', 'GR-C部'),
        );
    }

    public function test_gr_o_osaka_nested_cs_teams(): void
    {
        $this->assertSame(
            ['送迎課', 'CS課'],
            RegistryTeamByAssignment::optionsFor('GR部（グローバル部）', '大阪', 'GR-O部'),
        );
        $this->assertSame(
            ['固定現場チーム', 'エリア担当チーム'],
            RegistryTeamByAssignment::childOptionsFor(
                'GR部（グローバル部）',
                '大阪',
                'GR-O部',
                'CS課',
            ),
        );
        $this->assertTrue(RegistryTeamByAssignment::isValidTeam(
            'GR部（グローバル部）',
            '大阪',
            'GR-O部',
            '固定現場チーム',
        ));
    }

    public function test_split_stored_assignment(): void
    {
        $this->assertSame(
            ['section' => 'GR-C部', 'team' => '総務課'],
            RegistryTeamByAssignment::splitStoredAssignment(
                'GR-C_大阪,GR-総務課_大阪',
                'GR部（グローバル部）',
                '大阪',
            ),
        );
        $this->assertSame(
            ['section' => null, 'team' => '法人チーム'],
            RegistryTeamByAssignment::splitStoredAssignment('法人チーム'),
        );
    }
}
