<?php

namespace Tests\Unit;

use App\Support\RegistryGrAssignment;
use App\Support\RegistryOrgAssignment;
use PHPUnit\Framework\TestCase;

class RegistryGrAssignmentTest extends TestCase
{
    public function test_divisions_use_display_labels_without_location_suffix(): void
    {
        $this->assertSame(
            ['GR-C部', 'GR-S部', 'GR-M部', 'GR-O部'],
            RegistryGrAssignment::sectionOptionsFor('大阪'),
        );
    }

    public function test_gr_c_osaka_teams_use_display_labels(): void
    {
        $this->assertSame(
            ['総務課', '教育課'],
            RegistryGrAssignment::teamOptionsFor('大阪', 'GR-C部'),
        );
    }

    public function test_gr_o_osaka_nested_cs_teams(): void
    {
        $this->assertSame(
            ['送迎課', 'CS課'],
            RegistryGrAssignment::teamOptionsFor('大阪', 'GR-O部'),
        );
        $this->assertSame(
            ['固定現場チーム', 'エリア担当チーム'],
            RegistryGrAssignment::teamChildOptionsFor('大阪', 'GR-O部', 'CS課'),
        );
    }

    public function test_resolve_form_labels_to_canonical_storage_values(): void
    {
        $this->assertSame('GR-C_大阪', RegistryGrAssignment::resolveSectionToCanonical('GR-C部', '大阪'));
        $this->assertSame('GR-総務課_大阪', RegistryGrAssignment::resolveTeamToCanonical('大阪', 'GR-C部', '総務課'));
        $this->assertSame(
            'GR-O CS課 固定現場チーム_大阪',
            RegistryGrAssignment::resolveTeamToCanonical('大阪', 'GR-O部', '固定現場チーム'),
        );
    }

    public function test_to_form_values_converts_canonical_storage_back_to_labels(): void
    {
        $this->assertSame(
            ['section' => 'GR-O部', 'team' => '固定現場チーム'],
            RegistryGrAssignment::toFormValues('大阪', 'GR-O_大阪', 'GR-O CS課 固定現場チーム_大阪'),
        );
    }

    public function test_roster_department_label_uses_jurisdiction_and_global_business_department_for_gr(): void
    {
        $this->assertSame(
            '大阪グローバル事業部',
            RegistryGrAssignment::rosterDepartmentLabel('大阪', RegistryGrAssignment::DEPARTMENT),
        );
        $this->assertSame(
            '東京グローバル事業部',
            RegistryGrAssignment::rosterDepartmentLabel('東京', RegistryGrAssignment::DEPARTMENT),
        );
    }

    public function test_roster_department_label_keeps_non_gr_and_existing_global_department(): void
    {
        $this->assertSame('通信事業部', RegistryGrAssignment::rosterDepartmentLabel('大阪', '通信事業部'));
        $this->assertSame(
            '大阪グローバル事業部',
            RegistryGrAssignment::rosterDepartmentLabel('大阪', '大阪グローバル事業部'),
        );
        $this->assertSame(
            RegistryGrAssignment::DEPARTMENT,
            RegistryGrAssignment::rosterDepartmentLabel('', RegistryGrAssignment::DEPARTMENT),
        );
    }

    public function test_resolve_for_storage_converts_registry_form_submission(): void
    {
        [$section, $team, $sectionPrimary] = RegistryOrgAssignment::resolveForStorage(
            'GR部（グローバル部）',
            '大阪',
            'GR-O部',
            '固定現場チーム',
        );

        $this->assertSame('GR-O_大阪', $section);
        $this->assertSame('GR-O CS課 固定現場チーム_大阪', $team);
        $this->assertSame('GR-O_大阪', $sectionPrimary);
    }
}
