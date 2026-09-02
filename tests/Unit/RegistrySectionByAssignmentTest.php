<?php

namespace Tests\Unit;

use App\Support\RegistrySectionByAssignment;
use PHPUnit\Framework\TestCase;

class RegistrySectionByAssignmentTest extends TestCase
{
    public function test_accounting_department_sections_do_not_require_location(): void
    {
        $this->assertSame(
            ['経理課', '総務課'],
            RegistrySectionByAssignment::optionsFor('経理部', ''),
        );
    }

    public function test_hr_department_includes_marketing_section(): void
    {
        $this->assertContains(
            '広報・マーケティング課',
            RegistrySectionByAssignment::optionsFor('人事部', '大阪'),
        );
    }

    public function test_admin_department_sections_depend_on_location(): void
    {
        $this->assertSame(
            ['業務課', '労務法務課', '情報管理課'],
            RegistrySectionByAssignment::optionsFor('管理部', '大阪'),
        );
        $this->assertSame(
            ['業務課'],
            RegistrySectionByAssignment::optionsFor('管理部', '東京'),
        );
        $this->assertSame([], RegistrySectionByAssignment::optionsFor('管理部', ''));
    }

    public function test_sales_department_sections_depend_on_location(): void
    {
        $this->assertSame(
            ['人材育成課', 'SS課', '営業1課', '営業2課', '営業3課', '営業4課'],
            RegistrySectionByAssignment::optionsFor('営業部', '大阪'),
        );
        $this->assertSame(
            ['営業1課', '営業2課', 'SS課'],
            RegistrySectionByAssignment::optionsFor('営業部', '東京'),
        );
        $this->assertSame(
            ['営業課_名古屋', 'SS課_名古屋'],
            RegistrySectionByAssignment::optionsFor('営業部', '名古屋'),
        );
        $this->assertSame(
            ['営業課_福岡', 'SS課_福岡'],
            RegistrySectionByAssignment::optionsFor('営業部', '福岡'),
        );
    }

    public function test_global_department_sections_depend_on_location(): void
    {
        $this->assertSame(
            ['GR-C部', 'GR-S部', 'GR-M部', 'GR-O部'],
            RegistrySectionByAssignment::optionsFor('GR部（グローバル部）', '大阪'),
        );
        $this->assertSame(
            ['GR-S部', '管理部業務課'],
            RegistrySectionByAssignment::optionsFor('GR部（グローバル部）', 'ベトナム'),
        );
    }

    public function test_section_field_is_always_shown(): void
    {
        $this->assertTrue(RegistrySectionByAssignment::shouldShowField('', ''));
        $this->assertTrue(RegistrySectionByAssignment::shouldShowField('Food Sales部', '大阪'));
        $this->assertTrue(RegistrySectionByAssignment::shouldShowField('経理部', ''));
    }

    public function test_department_without_rules_returns_standalone_sections(): void
    {
        $this->assertSame(
            ['庶務課'],
            RegistrySectionByAssignment::optionsFor('Food Sales部', '大阪'),
        );
        $this->assertFalse(RegistrySectionByAssignment::hasRules('情報システム部'));
    }

    public function test_empty_department_returns_standalone_sections(): void
    {
        $this->assertSame(
            ['庶務課'],
            RegistrySectionByAssignment::optionsFor('', '大阪'),
        );
    }

    public function test_for_select_appends_legacy_section(): void
    {
        $options = RegistrySectionByAssignment::forSelect('経理部', '大阪', '旧課');

        $this->assertSame('旧課', $options[array_key_last($options)]);
        $this->assertContains('経理課', $options);
    }
}
