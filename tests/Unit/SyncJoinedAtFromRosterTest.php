<?php

namespace Tests\Unit;

use App\Console\Commands\SyncJoinedAtFromRosterCommand;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\EmployeeRosterCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncJoinedAtFromRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_date_supports_roster_formats(): void
    {
        $this->assertSame('2026-04-20', EmployeeRosterCsv::parseDate('4/20/2026'));
        $this->assertSame('2026-04-20', EmployeeRosterCsv::parseDate('2026/4/20'));
        $this->assertSame('2023-11-06', EmployeeRosterCsv::parseDate('2023/11/6'));
        $this->assertNull(EmployeeRosterCsv::parseDate(''));
        $this->assertNull(EmployeeRosterCsv::parseDate('―'));
    }

    public function test_resolve_joined_at_falls_back_to_planned_date(): void
    {
        $this->assertSame(
            '2024-03-11',
            EmployeeRosterCsv::resolveJoinedAt('', '2024/3/11'),
        );
        $this->assertSame(
            '2026-04-20',
            EmployeeRosterCsv::resolveJoinedAt('4/20/2026', '2024/3/11'),
        );
    }

    public function test_name_matching_accepts_spaced_and_kana_variants(): void
    {
        $user = User::factory()->create([
            'name' => '中谷 亮介',
            'last_name' => '中谷',
            'first_name' => '亮介',
            'email' => 'ryosuke_nakatani@careearth.info',
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
            'name_kana' => '中谷亮介',
            'joined_at' => '2026-06-24',
        ]);

        $user->load('profile');

        $this->assertTrue(EmployeeRosterCsv::nameMatchesUser([
            'name' => '中谷 亮介',
            'english_name' => 'Nakatani Ryosuke',
        ], $user));
    }

    public function test_command_updates_joined_at_when_email_and_name_match(): void
    {
        $user = User::factory()->create([
            'name' => '中谷 亮介',
            'last_name' => '中谷',
            'first_name' => '亮介',
            'email' => 'ryosuke_nakatani@careearth.info',
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
            'name_kana' => '中谷亮介',
            'joined_at' => '2026-06-24',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,社用アドレス,入社日
中谷 亮介,Nakatani Ryosuke,ryosuke_nakatani@careearth.info,4/20/2026
CSV
        );

        $code = Artisan::call(SyncJoinedAtFromRosterCommand::class, [
            'file' => $path,
        ]);

        $this->assertSame(0, $code);
        $this->assertSame(
            '2026-04-20',
            $user->fresh()->profile?->joined_at?->toDateString(),
        );
    }

    public function test_command_skips_when_name_does_not_match(): void
    {
        $user = User::factory()->create([
            'name' => '別人 太郎',
            'email' => 'ryosuke_nakatani@careearth.info',
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
            'joined_at' => '2026-06-24',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,社用アドレス,入社日
中谷 亮介,Nakatani Ryosuke,ryosuke_nakatani@careearth.info,4/20/2026
CSV
        );

        Artisan::call(SyncJoinedAtFromRosterCommand::class, [
            'file' => $path,
        ]);

        $this->assertSame(
            '2026-06-24',
            $user->fresh()->profile?->joined_at?->toDateString(),
        );
    }

    public function test_command_updates_joined_at_when_email_only_flag_is_set(): void
    {
        $user = User::factory()->create([
            'name' => '別人 太郎',
            'email' => 'ryosuke_nakatani@careearth.info',
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
            'joined_at' => '2026-06-24',
        ]);

        $path = $this->writeRosterCsv(<<<'CSV'
名前,Name,社用アドレス,入社日
中谷 亮介,Nakatani Ryosuke,ryosuke_nakatani@careearth.info,4/20/2026
CSV
        );

        Artisan::call(SyncJoinedAtFromRosterCommand::class, [
            'file' => $path,
            '--match-email-only' => true,
        ]);

        $this->assertSame(
            '2026-04-20',
            $user->fresh()->profile?->joined_at?->toDateString(),
        );
    }

    private function writeRosterCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'roster_');
        $csvPath = $path.'.csv';
        rename($path, $csvPath);
        file_put_contents($csvPath, $contents);

        return $csvPath;
    }
}
