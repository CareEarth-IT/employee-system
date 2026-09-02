<?php

namespace Tests\Unit;

use App\Support\NationalityOptions;
use Tests\TestCase;

class NationalityOptionsTest extends TestCase
{
    public function test_map_code_to_name(): void
    {
        $this->assertSame('日本', NationalityOptions::mapCodeToName('JP'));
        $this->assertSame('ベトナム', NationalityOptions::mapCodeToName('VN'));
        $this->assertSame('バングラデシュ', NationalityOptions::mapCodeToName('BD'));
        $this->assertNull(NationalityOptions::mapCodeToName('XX'));
    }

    public function test_to_display_name_converts_code_or_name(): void
    {
        $this->assertSame('日本', NationalityOptions::toDisplayName('JP'));
        $this->assertSame('日本', NationalityOptions::toDisplayName('日本'));
        $this->assertSame('中国', NationalityOptions::toDisplayName('cn'));
        $this->assertNull(NationalityOptions::toDisplayName(''));
        $this->assertNull(NationalityOptions::toDisplayName(null));
        $this->assertNull(NationalityOptions::toDisplayName('US'));
    }

    public function test_normalize_accepts_code_or_name(): void
    {
        $this->assertSame('日本', NationalityOptions::normalize('JP'));
        $this->assertSame('日本', NationalityOptions::normalize('日本'));
        $this->assertSame('中国', NationalityOptions::normalize('cn'));
        $this->assertNull(NationalityOptions::normalize(''));
        $this->assertNull(NationalityOptions::normalize(null));
    }

    public function test_all_names_are_unique(): void
    {
        $names = NationalityOptions::names();

        $this->assertCount(count(NationalityOptions::CODE_TO_NAME), $names);
        $this->assertSame($names, array_values(array_unique($names)));
    }
}
