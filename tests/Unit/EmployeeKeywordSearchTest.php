<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\EmployeeKeywordSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeKeywordSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_like_pattern_wraps_plain_keyword(): void
    {
        $this->assertSame('%山田%', EmployeeKeywordSearch::toLikePattern('山田'));
    }

    public function test_to_like_pattern_converts_asterisk_to_wildcard(): void
    {
        $this->assertSame('%山田%太郎%', EmployeeKeywordSearch::toLikePattern('山田*太郎'));
    }

    public function test_remove_spaces_strips_half_and_full_width_spaces(): void
    {
        $this->assertSame('山田太郎', EmployeeKeywordSearch::removeSpaces('山田 太郎'));
        $this->assertSame('山田太郎', EmployeeKeywordSearch::removeSpaces('山田　太郎'));
    }

    public function test_apply_matches_name_without_space_between_last_and_first(): void
    {
        $employee = User::factory()->create([
            'last_name' => '山田',
            'first_name' => '太郎',
            'email' => 'yamada@careearth.info',
        ]);
        AffiliationHistory::create([
            'user_id' => $employee->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
        ]);

        $matched = User::query()->tap(fn ($query) => EmployeeKeywordSearch::apply($query, '山田太郎'))->get();
        $this->assertCount(1, $matched);
        $this->assertSame($employee->id, $matched->first()->id);

        $matchedWithSpace = User::query()->tap(fn ($query) => EmployeeKeywordSearch::apply($query, '山田 太郎'))->get();
        $this->assertCount(1, $matchedWithSpace);
        $this->assertSame($employee->id, $matchedWithSpace->first()->id);
    }

    public function test_apply_searches_hr_detail_fields(): void
    {
        $employee = User::factory()->create([
            'last_name' => '別',
            'first_name' => 'ユーザー',
            'email' => 'other@careearth.info',
        ]);
        EmployeeHrDetail::create([
            'user_id' => $employee->id,
            'gmail_address' => 'unique.gmail.search@example.com',
        ]);

        $matched = User::query()->tap(fn ($query) => EmployeeKeywordSearch::apply($query, 'unique.gmail.search'))->get();

        $this->assertCount(1, $matched);
        $this->assertSame($employee->id, $matched->first()->id);
    }

    public function test_apply_supports_prefix_wildcard(): void
    {
        $employee = User::factory()->create([
            'last_name' => '佐藤',
            'first_name' => '花子',
            'email' => 'sato@careearth.info',
        ]);

        $matched = User::query()->tap(fn ($query) => EmployeeKeywordSearch::apply($query, '佐藤*'))->get();

        $this->assertCount(1, $matched);
        $this->assertSame($employee->id, $matched->first()->id);
    }
}
