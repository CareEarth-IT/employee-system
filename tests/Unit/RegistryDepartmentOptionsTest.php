<?php

namespace Tests\Unit;

use App\Support\RegistryDepartmentOptions;
use PHPUnit\Framework\TestCase;

class RegistryDepartmentOptionsTest extends TestCase
{
    public function test_options_match_registry_department_list(): void
    {
        $this->assertSame([
            '経営企画室',
            '管理本部',
            '派遣事業部',
            '不動産事業部',
            '通信事業部',
            '特定技能事業部',
            '食品事業部',
            '美容事業部',
            '経理部',
            '情報システム部',
            '人事部',
            '大阪管理部',
            '大阪営業部',
            '大阪グローバル事業部',
            '東京営業部',
            '東京グローバル事業部',
            '東京管理部',
            '名古屋営業部',
            '名古屋グローバル事業部',
            '福岡営業部',
            '福岡グローバル事業部',
            'ベトナム支店',
            'M&A戦略推進部',
        ], RegistryDepartmentOptions::options());
    }

    public function test_dashboard_tabs_for_business_departments(): void
    {
        $this->assertSame(['telecom'], RegistryDepartmentOptions::dashboardTabsFor('通信事業部'));
        $this->assertSame(['specified-skills', 'real-estate'], RegistryDepartmentOptions::dashboardTabsFor('経理部'));
        $this->assertSame([], RegistryDepartmentOptions::dashboardTabsFor('経営企画室'));
    }

    public function test_for_select_appends_legacy_department(): void
    {
        $options = RegistryDepartmentOptions::forSelect('食品部');

        $this->assertSame('食品部', $options[array_key_last($options)]);
        $this->assertContains('食品事業部', $options);
        $this->assertNotContains('SS課_名古屋', $options);
    }
}
