<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ImportEmployeesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_import_does_not_overwrite_existing_user_or_profile_by_default(): void
    {
        $user = $this->existingUserWithProfile('2019-04-01');

        $this->runImport('existing@careearth.info,既存,太郎,営業部,営業課,,CareEarth,大阪,00100');

        $user->refresh();
        $this->assertSame('既存 太郎', $user->name);
        $this->assertSame('既存太郎', $user->profile?->name_kana);
    }

    public function test_bulk_import_does_not_overwrite_existing_joined_at(): void
    {
        $user = $this->existingUserWithProfile('2019-04-01');

        $this->runImport('existing@careearth.info,既存,太郎,営業部,営業課,,CareEarth,大阪,00100');

        $this->assertSame(
            '2019-04-01',
            $user->fresh()->profile?->joined_at?->toDateString(),
        );
    }

    public function test_bulk_import_does_not_overwrite_existing_affiliation_by_default(): void
    {
        $user = $this->existingUserWithProfile('2019-04-01');

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2019-04-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '人事部',
            'section' => '人事課',
            'location' => '大阪',
        ]);

        $this->runImport('existing@careearth.info,既存,太郎,営業部,営業課,,CareEarth,大阪,00100');

        $affiliation = $user->fresh()->currentAffiliation();
        $this->assertSame('人事部', $affiliation?->department);
        $this->assertSame('人事課', $affiliation?->section);
    }

    public function test_sync_affiliations_updates_unlocked_records_only(): void
    {
        $user = $this->existingUserWithProfile('2019-04-01');

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2019-04-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '人事部',
            'section' => '人事課',
            'location' => '大阪',
            'import_locked' => true,
        ]);

        $this->runImport(
            'existing@careearth.info,既存,太郎,営業部,営業課,,CareEarth,大阪,00100',
            ['--sync-affiliations' => true],
        );

        $this->assertSame('人事部', $user->fresh()->currentAffiliation()?->department);
    }

    public function test_sync_affiliations_updates_unlocked_existing_affiliation(): void
    {
        $user = $this->existingUserWithProfile('2019-04-01');

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2019-04-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '人事部',
            'section' => '人事課',
            'location' => '大阪',
            'import_locked' => false,
        ]);

        $this->runImport(
            'existing@careearth.info,既存,太郎,営業部,営業課,,CareEarth,大阪,00100',
            ['--sync-affiliations' => true],
        );

        $affiliation = $user->fresh()->currentAffiliation();
        $this->assertSame('営業部', $affiliation?->department);
        $this->assertSame('営業課', $affiliation?->section);
    }

    public function test_affiliation_edit_marks_record_import_locked(): void
    {
        $editor = User::factory()->create();
        AffiliationHistory::create([
            'user_id' => $editor->id,
            'start_date' => '2020-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '人事部',
            'section' => '人事課',
            'position' => '正社員',
            'location' => '大阪',
        ]);

        $target = User::factory()->create();
        $affiliation = AffiliationHistory::create([
            'user_id' => $target->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '通信部',
            'location' => '大阪',
        ]);

        $this->actingAs($editor->fresh())
            ->put(route('affiliations.update', $affiliation), [
                'is_current' => '1',
                'start_date' => '2024-01-01',
                'company' => 'CareEarth',
                'location' => '大阪',
                'department' => '不動産部',
                'section' => '',
                'position' => '',
                'job_description' => '',
            ])
            ->assertRedirect();

        $this->assertTrue($affiliation->fresh()->import_locked);
        $this->assertSame('不動産部', $affiliation->fresh()->department);
    }

    public function test_profile_edit_marks_record_import_locked(): void
    {
        $user = User::factory()->create();
        EmployeeProfile::create([
            'user_id' => $user->id,
            'name_kana' => 'テスト太郎',
        ]);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'english_name' => 'Test Taro',
                'name_kana' => 'テスト太郎',
            ])
            ->assertRedirect();

        $this->assertTrue($user->fresh()->profile?->import_locked);
        $this->assertTrue($user->fresh()->import_locked);
    }

    private function existingUserWithProfile(string $joinedAt): User
    {
        $user = User::factory()->create([
            'email' => 'existing@careearth.info',
            'name' => '既存 太郎',
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
            'name_kana' => '既存太郎',
            'joined_at' => $joinedAt,
            'nationality' => '日本',
        ]);

        return $user;
    }

    /**
     * @param  array<string, bool>  $options
     */
    private function runImport(string $row, array $options = []): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'employees-import-');

        file_put_contents($csv, implode("\n", [
            'email,姓,名,部,課,役職,会社,拠点,社員番号',
            $row,
        ]));

        try {
            Artisan::call('employee:import-bulk', [
                'file' => $csv,
                ...$options,
            ]);
        } finally {
            @unlink($csv);
        }
    }
}
