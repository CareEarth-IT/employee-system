<?php

namespace Tests\Unit;

use App\Support\RegistryDepartmentOptions;
use PHPUnit\Framework\TestCase;

class RegistryDepartmentOptionsTest extends TestCase
{
    public function test_options_match_registry_department_list(): void
    {
        $this->assertSame([
            'M&A戦略推進部',
            '美容事業部',
            '不動産事業部',
            '通信事業部',
            '特定技能事業部',
            '経理部',
            '情報システム部',
            '人事部',
            '食品事業部',
            '管理部',
            '管理本部',
            '人材派遣事業部',
            'GR部（グローバル部）',
        ], RegistryDepartmentOptions::options());
    }

    public function test_dashboard_tabs_for_business_departments(): void
    {
        $this->assertSame(['food'], RegistryDepartmentOptions::dashboardTabsFor('食品事業部'));
        $this->assertSame([], RegistryDepartmentOptions::dashboardTabsFor('Food Sales部'));
        $this->assertSame(['specified-skills', 'real-estate'], RegistryDepartmentOptions::dashboardTabsFor('経理部'));
        $this->assertSame(['dispatch'], RegistryDepartmentOptions::dashboardTabsFor('人材派遣事業部'));
        $this->assertSame(['dispatch'], RegistryDepartmentOptions::dashboardTabsFor('営業部'));
    }

    public function test_legacy_sales_department_normalizes_to_staffing(): void
    {
        $this->assertSame(
            '人材派遣事業部',
            RegistryDepartmentOptions::normalizeDepartment('営業部'),
        );
        $this->assertSame(
            ['department' => '人材派遣事業部', 'section' => null],
            RegistryDepartmentOptions::resolveAffiliation('営業部'),
        );
        $this->assertSame(
            '人材派遣事業部',
            RegistryDepartmentOptions::registryFormDepartment('営業部', null),
        );
        $this->assertContains(
            '人材派遣事業部',
            RegistryDepartmentOptions::forSelect('営業部'),
        );
        $this->assertNotContains(
            '営業部',
            RegistryDepartmentOptions::forSelect('営業部'),
        );
    }

    public function test_for_select_appends_legacy_department(): void
    {
        $options = RegistryDepartmentOptions::forSelect('食品部');

        $this->assertSame('食品部', $options[array_key_last($options)]);
        $this->assertContains('食品事業部', $options);
        $this->assertNotContains('Food Sales部', $options);
        $this->assertNotContains('SS課_名古屋', $options);
    }

    public function test_administrative_affairs_section_maps_to_management_headquarters(): void
    {
        $this->assertSame(
            ['department' => '管理本部', 'section' => null],
            RegistryDepartmentOptions::resolveAffiliation('情報システム部', '庶務課'),
        );
        $this->assertSame(
            ['department' => '管理本部', 'section' => null],
            RegistryDepartmentOptions::resolveAffiliation('管理本部', '庶務課'),
        );
    }

    public function test_management_headquarters_registry_form_department(): void
    {
        $this->assertSame(
            '管理本部',
            RegistryDepartmentOptions::registryFormDepartment('管理本部', '庶務課'),
        );
    }
}
