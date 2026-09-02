<?php

namespace Tests\Unit;

use App\Support\RegistryDepartmentOptions;
use App\Support\RegistrySectionOptions;
use PHPUnit\Framework\TestCase;

class RegistrySectionOptionsTest extends TestCase
{
    public function test_options_exclude_registry_departments(): void
    {
        $options = RegistrySectionOptions::options();

        $this->assertContains('人事課', $options);
        $this->assertContains('経理課', $options);
        $this->assertContains('SS課_名古屋', $options);
        $this->assertContains('ベトナム支店', $options);
        $this->assertContains('大阪グローバル事業部', $options);
        $this->assertNotContains('Food Sales部', $options);
        $this->assertNotContains('出荷チーム', $options);

        foreach ($options as $option) {
            $this->assertNotContains($option, RegistryDepartmentOptions::options());
        }
    }

    public function test_for_select_appends_legacy_section(): void
    {
        $options = RegistrySectionOptions::forSelect('旧課');

        $this->assertSame('旧課', $options[array_key_last($options)]);
        $this->assertContains('人事課', $options);
    }
}
