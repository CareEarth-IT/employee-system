<?php

namespace App\Support;

use App\Models\AffiliationHistory;
use App\Models\ScheduledAffiliationChange;

final class ScheduledAffiliationChangeDiff
{
    /** @var list<string> */
    public const OVERRIDE_FIELDS = [
        'company',
        'location',
        'department',
        'section',
        'position',
        'job_description',
    ];

    /** @var array<string, string> */
    public const FIELD_LABELS = [
        'company' => '所属会社',
        'location' => '管轄',
        'department' => '部',
        'section' => '課',
        'position' => '役職',
        'job_description' => '業務内容',
    ];

    /**
     * 現行所属と異なる項目だけを返す（未変更は含めない）。
     * 空文字は「クリア」を意味し、null（未変更）と区別する。
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, string>
     */
    public static function overrides(?AffiliationHistory $current, array $validated): array
    {
        $overrides = [];

        foreach (self::OVERRIDE_FIELDS as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $submitted = self::normalize($validated[$field] ?? null);
            $existing = self::normalize($current?->{$field} ?? null);

            if ($submitted === $existing) {
                continue;
            }

            // クリアは空文字で保持（DB の null = 未変更）
            $overrides[$field] = $submitted ?? '';
        }

        return $overrides;
    }

    /**
     * @return array<string, mixed>
     */
    public static function attributesForCreate(?AffiliationHistory $current, array $validated): array
    {
        return [
            'effective_date' => $validated['effective_date'],
            ...array_fill_keys(self::OVERRIDE_FIELDS, null),
            ...self::overrides($current, $validated),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function attributesForUpdate(?AffiliationHistory $current, array $validated): array
    {
        return self::attributesForCreate($current, $validated);
    }

    public static function hasOverride(ScheduledAffiliationChange $change, string $field): bool
    {
        return array_key_exists($field, $change->getAttributes())
            && $change->getAttributes()[$field] !== null;
    }

    /**
     * フォーム初期値: 現行 + 変更予定の上書き。
     *
     * @return array{
     *     company: ?string,
     *     location: ?string,
     *     department: ?string,
     *     section: ?string,
     *     position: ?string,
     *     job_description: ?string
     * }
     */
    public static function formValues(?AffiliationHistory $current, ?ScheduledAffiliationChange $schedule): array
    {
        $values = [
            'company' => $current?->company,
            'location' => $current?->location,
            'department' => $current?->department,
            'section' => $current?->section,
            'position' => $current?->position,
            'job_description' => $current?->job_description,
        ];

        if ($schedule === null) {
            return $values;
        }

        foreach (self::OVERRIDE_FIELDS as $field) {
            if (self::hasOverride($schedule, $field)) {
                $raw = $schedule->getAttributes()[$field];
                $values[$field] = $raw === '' ? null : $raw;
            }
        }

        return $values;
    }

    /**
     * 反映時の所属属性: 現行を土台に変更項目だけ上書き。
     *
     * @return array<string, mixed>
     */
    public static function resolvedAffiliationAttributes(
        ScheduledAffiliationChange $change,
        ?AffiliationHistory $current,
    ): array {
        $attributes = [
            'company' => $current?->company,
            'location' => $current?->location,
            'department' => $current?->department,
            'section' => $current?->section,
            'position' => $current?->position,
            'job_description' => $current?->job_description,
        ];

        foreach (self::OVERRIDE_FIELDS as $field) {
            if (! self::hasOverride($change, $field)) {
                continue;
            }

            $raw = $change->getAttributes()[$field];
            $attributes[$field] = $raw === '' ? null : $raw;
        }

        return $attributes;
    }

    /**
     * @return list<array{field: string, label: string, value: string}>
     */
    public static function changedSummaries(ScheduledAffiliationChange $change): array
    {
        $summaries = [];

        foreach (self::OVERRIDE_FIELDS as $field) {
            if (! self::hasOverride($change, $field)) {
                continue;
            }

            $raw = $change->getAttributes()[$field];
            $display = $raw === '' || $raw === null ? '—' : (string) $raw;

            if ($field === 'section') {
                $split = RegistryOrgAssignment::splitForRegistryForm(
                    $raw === '' ? null : (string) $raw,
                    $change->department,
                    $change->location,
                );
                $parts = array_filter([
                    $split['section'] ?? null,
                    $split['team'] ?? null,
                ], static fn ($part) => $part !== null && $part !== '');
                $display = $parts === [] ? '—' : implode(' / ', $parts);
            }

            $summaries[] = [
                'field' => $field,
                'label' => self::FIELD_LABELS[$field] ?? $field,
                'value' => $display,
            ];
        }

        return $summaries;
    }

    private static function normalize(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
