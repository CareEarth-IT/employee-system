<?php

namespace App\Support;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;

final class AffiliationHrDetailSync
{
    /** @var list<string> */
    public const HR_ORG_FIELDS = [
        'affiliation_code',
        'jurisdiction',
        'department_primary',
        'section_primary',
        'position_primary',
    ];

    /** @var list<string> */
    public const AFFILIATION_ORG_FIELDS = [
        'company',
        'location',
        'department',
        'section',
        'position',
    ];

    /**
     * @return array<string, ?string>
     */
    public static function hrDetailAttributesFromAffiliation(AffiliationHistory $affiliation): array
    {
        [$department, $location] = RegistryGrAssignment::normalizeOrgStorage(
            $affiliation->department,
            $affiliation->location,
            $affiliation->section,
        );

        $orgPrimary = RegistryOrgAssignment::hrDetailPrimaryFromAffiliation(
            $department,
            $location,
            $affiliation->section,
        );

        return [
            'affiliation_code' => User::mapCompanyToAffiliationCode($affiliation->company),
            'jurisdiction' => $orgPrimary['jurisdiction'],
            'department_primary' => $orgPrimary['department_primary'],
            'section_primary' => $orgPrimary['section_primary'],
            'position_primary' => self::nullablePosition($affiliation->position),
        ];
    }

    /**
     * @return array<string, ?string>
     */
    public static function affiliationAttributesFromHrDetail(EmployeeHrDetail $detail): array
    {
        [$departmentPrimary, $jurisdiction] = RegistryGrAssignment::normalizeOrgStorage(
            $detail->department_primary,
            $detail->jurisdiction,
            $detail->section_primary,
        );

        $split = RegistryOrgAssignment::splitForRegistryForm(
            $detail->section_primary,
            $departmentPrimary,
            $jurisdiction,
        );

        [$sectionStored, $teamStored] = array_slice(
            RegistryOrgAssignment::resolveForStorage(
                (string) $departmentPrimary,
                (string) $jurisdiction,
                $split['section'],
                $split['team'],
            ),
            0,
            2,
        );

        return [
            'company' => User::mapAffiliationCodeToCompany((string) ($detail->affiliation_code ?? '')),
            'location' => self::nullableString($jurisdiction),
            'department' => self::nullableString($departmentPrimary),
            'section' => RegistryOrgAssignment::combine($sectionStored, $teamStored),
            'position' => self::nullablePosition($detail->position_primary),
        ];
    }

    /**
     * CSV 取込など、所属部署側を更新したときだけ詳細情報へ反映する。
     * 通常の所属部署登録では呼ばない（詳細情報が正）。
     */
    public static function syncHrDetailFromAffiliation(User $user, ?AffiliationHistory $affiliation = null): void
    {
        $affiliation ??= $user->currentAffiliation();

        if ($affiliation === null || ! $affiliation->isEnrolled()) {
            return;
        }

        $current = $user->fresh(['affiliationHistories'])?->currentAffiliation();

        if ($current?->id !== $affiliation->id) {
            return;
        }

        EmployeeHrDetail::query()->updateOrCreate(
            ['user_id' => $user->id],
            self::hrDetailAttributesFromAffiliation($affiliation),
        );
    }

    /**
     * 詳細情報を正として、在籍中の所属部署1件だけを更新する。過去の所属履歴は変更しない。
     */
    public static function syncAffiliationFromHrDetail(User $user, ?EmployeeHrDetail $detail = null): void
    {
        $affiliation = $user->currentAffiliation();

        if ($affiliation === null || ! $affiliation->isEnrolled()) {
            return;
        }

        $detail ??= $user->hrDetail;

        if ($detail === null) {
            return;
        }

        $concurrent = $user->concurrentAffiliations();
        if ($concurrent !== null) {
            $concurrent->get(0)?->update([
                ...self::affiliationAttributesFromHrDetail($detail),
                'import_locked' => true,
            ]);
            $second = $concurrent->get(1);
            if ($second instanceof AffiliationHistory) {
                $second->update([
                    ...self::affiliationAttributesFromHrDetailSecondary($detail, $second),
                    'import_locked' => true,
                ]);
            }

            return;
        }

        $affiliation->update([
            ...self::affiliationAttributesFromHrDetail($detail),
            'import_locked' => true,
        ]);
    }

    /**
     * 詳細情報の部署②が未作成の所属なら在籍所属を追加し、③以降は追加行として保存する。
     *
     * @param  list<array<string, mixed>>  $extraRows
     */
    public static function syncAdditionalOrgRows(
        User $user,
        EmployeeHrDetail $detail,
        array $extraRows,
        bool $extrasSubmitted,
    ): void {
        self::ensureSecondaryAffiliation($user, $detail);

        if (! $extrasSubmitted) {
            return;
        }

        $user->unsetRelation('affiliationHistories');
        $existingExtras = ($user->concurrentAffiliations() ?? collect())->slice(2)->keyBy('id');
        $keptIds = [];

        foreach ($extraRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $stored = self::storedOrgFromInput(
                (string) ($row['department'] ?? ''),
                (string) ($row['section'] ?? ''),
                (string) ($row['team'] ?? ''),
                $row['position'] ?? null,
                $detail->jurisdiction,
            );

            if ($stored['department'] === null && $stored['section'] === null && $stored['position'] === null) {
                continue;
            }

            $id = (int) ($row['affiliation_id'] ?? 0);
            $affiliation = $id > 0 ? $existingExtras->get($id) : null;

            if ($affiliation instanceof AffiliationHistory) {
                $affiliation->update([
                    ...$stored,
                    'import_locked' => true,
                ]);
                $keptIds[] = $affiliation->id;

                continue;
            }

            $primary = $user->concurrentAffiliations()?->first() ?? $user->currentAffiliation();
            $created = AffiliationHistory::create([
                'user_id' => $user->id,
                'start_date' => $primary?->start_date?->toDateString(),
                'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                'company' => $primary?->company,
                'location' => self::nullableString($detail->jurisdiction) ?? $primary?->location,
                ...$stored,
                'import_locked' => true,
                'is_primary' => false,
            ]);
            $keptIds[] = $created->id;
            $user->unsetRelation('affiliationHistories');
        }

        foreach ($existingExtras as $extra) {
            if (! in_array($extra->id, $keptIds, true)) {
                $extra->delete();
            }
        }
    }

    private static function ensureSecondaryAffiliation(User $user, EmployeeHrDetail $detail): void
    {
        $position = self::nullablePosition($detail->position_secondary);
        $department = trim((string) $detail->department_secondary);
        $section = trim((string) $detail->section_secondary);

        if ($department === '' && $section === '' && $position === null) {
            return;
        }

        $user->unsetRelation('affiliationHistories');
        if (($user->concurrentAffiliations()?->count() ?? 0) >= 2) {
            return;
        }

        $primary = $user->currentAffiliation();
        if ($primary === null || ! $primary->isEnrolled()) {
            return;
        }

        $stored = self::affiliationAttributesFromHrDetailSecondary($detail, $primary);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => $primary->start_date?->toDateString(),
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => $primary->company,
            'location' => self::nullableString($detail->jurisdiction) ?? $primary->location,
            'department' => $stored['department'],
            'section' => $stored['section'],
            'position' => $stored['position'],
            'import_locked' => true,
            'is_primary' => false,
        ]);
    }

    /**
     * @return array{department: ?string, section: ?string, position: ?string}
     */
    private static function storedOrgFromInput(
        string $department,
        string $section,
        string $team,
        mixed $position,
        ?string $location,
    ): array {
        $department = RegistryDepartmentOptions::hrDetailDepartment($department, $section);
        [$sectionStored, $teamStored] = array_slice(
            RegistryOrgAssignment::resolveForStorage(
                (string) $department,
                (string) $location,
                $section,
                $team,
            ),
            0,
            2,
        );

        return [
            'department' => self::nullableString($department),
            'section' => RegistryOrgAssignment::combine($sectionStored, $teamStored),
            'position' => self::nullablePosition($position),
        ];
    }

    /**
     * 兼任の2件目。拠点は所属側を維持し、部署・課・役職だけ詳細情報の②から反映する。
     *
     * @return array<string, ?string>
     */
    private static function affiliationAttributesFromHrDetailSecondary(
        EmployeeHrDetail $detail,
        AffiliationHistory $affiliation,
    ): array {
        $location = trim((string) ($affiliation->location ?? ''));
        [$department, $normalizedLocation] = RegistryGrAssignment::normalizeOrgStorage(
            $detail->department_secondary,
            $location !== '' ? $location : null,
            $detail->section_secondary,
        );

        $split = RegistryOrgAssignment::splitForRegistryForm(
            $detail->section_secondary,
            $department,
            $normalizedLocation,
        );

        [$sectionStored, $teamStored] = array_slice(
            RegistryOrgAssignment::resolveForStorage(
                (string) $department,
                (string) $normalizedLocation,
                $split['section'],
                $split['team'],
            ),
            0,
            2,
        );

        return [
            'department' => self::nullableString($department),
            'section' => RegistryOrgAssignment::combine($sectionStored, $teamStored),
            'position' => self::nullablePosition($detail->position_secondary),
        ];
    }

    /**
     * @return array{
     *     changed: bool,
     *     current: array<string, ?string>,
     *     target: array<string, ?string>,
     * }|null
     */
    public static function currentAffiliationSyncPlan(User $user): ?array
    {
        $affiliation = $user->currentAffiliation();
        $detail = $user->hrDetail;

        if ($affiliation === null || ! $affiliation->isEnrolled() || $detail === null) {
            return null;
        }

        $target = self::affiliationAttributesFromHrDetail($detail);
        $current = [];

        foreach (self::AFFILIATION_ORG_FIELDS as $field) {
            $current[$field] = $affiliation->{$field};
        }

        return [
            'changed' => self::valuesDiffer($current, $target),
            'current' => $current,
            'target' => $target,
        ];
    }

    /**
     * @param  array<string, ?string>  $current
     * @param  array<string, ?string>  $target
     */
    private static function valuesDiffer(array $current, array $target): bool
    {
        foreach (array_keys($target) as $field) {
            if (self::normalizeValue($current[$field] ?? null) !== self::normalizeValue($target[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private static function nullablePosition(mixed $value): ?string
    {
        return AffiliationPositionSync::normalizeStoredPosition(is_string($value) || $value === null ? $value : (string) $value);
    }

    private static function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
