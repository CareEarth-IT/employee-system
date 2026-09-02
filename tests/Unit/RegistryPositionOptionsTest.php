<?php

namespace Tests\Unit;

use App\Support\RegistryPositionOptions;
use Tests\TestCase;

class RegistryPositionOptionsTest extends TestCase
{
    public function test_for_select_excludes_ippan_by_default(): void
    {
        $options = RegistryPositionOptions::forSelect();

        $this->assertNotContains('一般', $options);
        $this->assertContains('リーダー', $options);
    }

    public function test_for_select_includes_legacy_position(): void
    {
        $options = RegistryPositionOptions::forSelect('カスタム役職');

        $this->assertContains('リーダー', $options);
        $this->assertContains('カスタム役職', $options);
    }

    public function test_for_select_includes_existing_ippan(): void
    {
        $options = RegistryPositionOptions::forSelect('一般');

        $this->assertContains('一般', $options);
    }
}
