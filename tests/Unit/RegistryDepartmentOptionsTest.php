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
            'Food Sales部',
            'Food Retail部',
            'Food Logistic部',
            'Food GA部',
            '管理部',
            '営業部',
            'GR部（グローバル部）',
        ], RegistryDepartmentOptions::options());
    }

    public function test_dashboard_tabs_for_business_departments(): void
    {
        $this->assertSame(['food'], RegistryDepartmentOptions::dashboardTabsFor('Food Sales部'));
        $this->assertSame(['specified-skills', 'real-estate'], RegistryDepartmentOptions::dashboardTabsFor('経理部'));
        $this->assertSame([], RegistryDepartmentOptions::dashboardTabsFor('営業部'));
    }

    public function test_for_select_appends_legacy_department(): void
    {
        $options = RegistryDepartmentOptions::forSelect('食品部');

        $this->assertSame('食品部', $options[array_key_last($options)]);
        $this->assertContains('Food Sales部', $options);
        $this->assertNotContains('SS課_名古屋', $options);
    }

    public function test_administrative_affairs_section_maps_to_management_headquarters(): void
    {
        $this->assertSame(
            ['department' => '管理本部', 'section' => null],
            RegistryDepartmentOptions::resolveAffiliation('情報システム部', '庶務課'),
        );
    }
}
