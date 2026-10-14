<?php

namespace Tests\Unit;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_display_name_prefers_family_given_name_over_name_kana(): void
    {
        $user = User::factory()->create([
            'last_name' => '山田',
            'first_name' => '太郎',
            'name' => '山田 太郎',
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
            'name_kana' => 'ヤマダ タロウ',
            'english_name' => 'Taro Yamada',
        ]);

        $this->assertSame('山田 太郎', $user->fresh(['profile'])->displayName());
        $this->assertSame('山田 太郎', $user->fresh()->familyGivenName());
    }

    public function test_display_name_falls_back_to_name_kana_when_family_given_missing(): void
    {
        $user = User::factory()->create([
            'last_name' => null,
            'first_name' => null,
            'name' => '未設定',
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
            'name_kana' => 'ヤマダ タロウ',
        ]);

        $this->assertSame('ヤマダ タロウ', $user->fresh(['profile'])->displayName());
    }
}
