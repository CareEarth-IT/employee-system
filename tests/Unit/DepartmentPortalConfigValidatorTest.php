<?php

namespace Tests\Unit;

use App\Support\DepartmentPortalConfigValidator;
use Tests\TestCase;

class DepartmentPortalConfigValidatorTest extends TestCase
{
    public function test_valid_default_config_passes(): void
    {
        $validator = new DepartmentPortalConfigValidator;

        $this->assertTrue($validator->isValid());
        $this->assertSame([], $validator->errors());
    }

    public function test_detects_unknown_portal_tab_key(): void
    {
        config(['department_portals.unknown-tab' => [
            'label' => 'Test',
            'internal_url' => 'https://example.test',
            'proxy_path' => 'unknown-portal',
        ]]);

        $errors = (new DepartmentPortalConfigValidator)->errors();

        $this->assertNotEmpty($errors);
        $this->assertTrue(collect($errors)->contains(fn (string $e) => str_contains($e, 'unknown-tab')));
    }

    public function test_detects_duplicate_proxy_path(): void
    {
        config([
            'department_portals.dispatch.proxy_path' => 'same-portal',
            'department_portals.food.proxy_path' => 'same-portal',
        ]);

        $errors = (new DepartmentPortalConfigValidator)->errors();

        $this->assertTrue(collect($errors)->contains(fn (string $e) => str_contains($e, 'same-portal')));
    }
}
