<?php

namespace Tests\Unit;

use App\Support\DateInput;
use Carbon\Carbon;
use Tests\TestCase;

class DateInputTest extends TestCase
{
    public function test_normalizes_iso_and_slash_formats(): void
    {
        $this->assertSame('2020-04-01', DateInput::normalize('2020-04-01'));
        $this->assertSame('2020-04-01', DateInput::normalize('2020/4/1'));
        $this->assertSame('2020-04-01', DateInput::normalize(Carbon::parse('2020-04-01')));
    }

    public function test_rejects_invalid_dates(): void
    {
        $this->assertNull(DateInput::normalize('2020/13/01'));
        $this->assertNull(DateInput::normalize('not-a-date'));
        $this->assertNull(DateInput::normalize(''));
    }

    public function test_formats_display_value(): void
    {
        $this->assertSame('2020/4/1', DateInput::toDisplay('2020-04-01'));
    }
}
