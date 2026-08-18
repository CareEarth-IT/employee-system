<?php

namespace Tests\Unit;

use App\Support\CompanyPhone;
use Tests\TestCase;

class CompanyPhoneTest extends TestCase
{
    public function test_parse_splits_comma_separated_values(): void
    {
        $this->assertSame(
            ['080-1111-2222', '080-3333-4444'],
            CompanyPhone::parse('080-1111-2222, 080-3333-4444'),
        );
    }

    public function test_parse_splits_japanese_comma_and_dedupes(): void
    {
        $this->assertSame(
            ['080-1111-2222', '080-3333-4444'],
            CompanyPhone::parse('080-1111-2222、080-3333-4444, 080-1111-2222'),
        );
    }

    public function test_normalize_joins_multiple_phones(): void
    {
        $this->assertSame(
            '080-7049-2687, 080-7049-8801, 080-7049-8855, 080-4379-8399',
            CompanyPhone::normalize('080-7049-2687, 080-7049-8801, 080-7049-8855, 080-4379-8399'),
        );
    }

    public function test_normalize_returns_null_for_empty_value(): void
    {
        $this->assertNull(CompanyPhone::normalize(''));
        $this->assertNull(CompanyPhone::normalize('―'));
    }
}
