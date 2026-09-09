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
        $orgPrimary = RegistryOrgAssignment::hrDetailPrimaryFromAffiliation(
            $affiliation->department,
            $affiliation->location,
            $affiliation->section,
        );

        return [
            'affiliation_code' => User::mapCompanyToAffiliationCode($affiliation->company),
            'jurisdiction' => self::nullableString($affiliation->location),
            'department_primary' => $orgPrimary['department_primary'],
            'section_primary' => $orgPrimary['section_primary'],
            'position_primary' => self::nullableString($affiliation->position),
        ];
    }

    /**
     * @return array<string, ?string>
     */
    public static function affiliationAttributesFromHrDetail(EmployeeHrDetail $detail): array
    {
        $split = RegistryOrgAssignment::splitForRegistryForm(
            $detail->section_primary,
            $detail->department_primary,
            $detail->jurisdiction,
        );

        [$sectionStored, $teamStored] = array_slice(
            RegistryOrgAssignment::resolveForStorage(
                (string) ($detail->department_primary ?? ''),
                (string) ($detail->jurisdiction ?? ''),
                $split['section'],
                $split['team'],
            ),
            0,
            2,
        );

        return [
            'company' => User::mapAffiliationCodeToCompany((string) ($detail->affiliation_code ?? '')),
            'location' => self::nullableString($detail->jurisdiction),
            'department' => self::nullableString($detail->department_primary),
            'section' => RegistryOrgAssignment::combine($sectionStored, $teamStored),
            'position' => self::nullableString($detail->position_primary),
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

        $affiliation->update([
            ...self::affiliationAttributesFromHrDetail($detail),
            'import_locked' => true,
        ]);
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

    private static function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
