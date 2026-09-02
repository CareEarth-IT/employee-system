<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EmployeeKeywordSearch
{
    /** @var list<string> */
    private const USER_COLUMNS = [
        'name',
        'email',
        'last_name',
        'first_name',
        'employee_id',
    ];

    /** @var list<string> */
    private const PROFILE_COLUMNS = [
        'english_name',
        'name_kana',
        'abbreviated_name',
        'nationality',
        'languages',
        'self_introduction',
    ];

    /** @var list<string> */
    private const AFFILIATION_COLUMNS = [
        'company',
        'location',
        'department',
        'section',
        'position',
        'job_description',
    ];

    /** @var list<string> */
    private const HR_DETAIL_COLUMNS = [
        'name_kana_fullwidth',
        'name_kana_halfwidth',
        'affiliation_code',
        'employment_type',
        'employment_status',
        'residence_status',
        'residence_renewal_memo',
        'residence_card_renewal_status',
        'department_primary',
        'section_primary',
        'position_primary',
        'department_secondary',
        'section_secondary',
        'position_secondary',
        'jurisdiction',
        'gender',
        'phone',
        'primary_id',
        'personal_email',
        'gmail_address',
        'remarks',
        'address_as_of_jan1',
        'previous_withholding_slip',
        'resident_tax_switch_form',
        'money_forward_setup',
        'rakuraku_seisan_setup',
        'smarthr_setup',
        'business_card_onboarding',
        'employment_insurance_number',
        'health_pension_number',
        'dependent_add_social_insurance',
        'pc_manufacturer',
        'pc_model',
        'mac_address',
        'mobile_manufacturer',
        'company_phone',
        'resident_tax_transfer_form',
        'employment_insurance_withdrawal',
        'health_pension_withdrawal',
        'withholding_tax_slip',
        'separation_certificate',
        'resignation_certificate',
    ];

    /**
     * @param  Builder<\App\Models\User>  $query
     */
    public static function apply(Builder $query, string $keyword, bool $activeAffiliationOnly = true): void
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return;
        }

        $like = self::toLikePattern($keyword);
        $compactKeyword = self::removeSpaces($keyword);
        $compactLike = $compactKeyword !== '' ? self::toLikePattern($compactKeyword) : null;

        $query->where(function (Builder $keywordQuery) use ($like, $compactLike, $keyword, $activeAffiliationOnly) {
            self::applyColumnLikes($keywordQuery, self::USER_COLUMNS, $like);

            if ($compactLike !== null) {
                self::applyCompactNameMatch($keywordQuery, $compactLike);
            }

            $keywordQuery->orWhereHas('profile', function (Builder $profileQuery) use ($like, $compactLike) {
                self::applyColumnLikes($profileQuery, self::PROFILE_COLUMNS, $like);

                if ($compactLike !== null) {
                    self::applyCompactExpressionMatch($profileQuery, 'COALESCE(name_kana, \'\')', $compactLike);
                }
            });

            $keywordQuery->orWhereHas('affiliationHistories', function (Builder $affiliationQuery) use ($like, $activeAffiliationOnly) {
                if ($activeAffiliationOnly) {
                    $affiliationQuery->currentlyActive();
                }

                self::applyColumnLikes($affiliationQuery, self::AFFILIATION_COLUMNS, $like);
            });

            $keywordQuery->orWhereHas('hrDetail', function (Builder $hrDetailQuery) use ($like, $compactLike, $keyword) {
                self::applyColumnLikes($hrDetailQuery, self::HR_DETAIL_COLUMNS, $like);

                if ($compactLike !== null) {
                    foreach (['name_kana_fullwidth', 'name_kana_halfwidth'] as $kanaColumn) {
                        self::applyCompactExpressionMatch(
                            $hrDetailQuery,
                            'COALESCE('.$kanaColumn.', \'\')',
                            $compactLike,
                        );
                    }
                }

                self::applyPhoneDigitMatch($hrDetailQuery, $keyword);
            });
        });
    }

    public static function toLikePattern(string $keyword): string
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return '%%';
        }

        $escaped = addcslashes($keyword, '\\%_');
        $pattern = str_replace('*', '%', $escaped);

        if (! str_contains($pattern, '%')) {
            return '%'.$pattern.'%';
        }

        if (! str_starts_with($pattern, '%')) {
            $pattern = '%'.$pattern;
        }

        if (! str_ends_with($pattern, '%')) {
            $pattern .= '%';
        }

        return $pattern;
    }

    public static function removeSpaces(string $value): string
    {
        return preg_replace('/[\s　]+/u', '', $value) ?? '';
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $columns
     */
    private static function applyColumnLikes(Builder $query, array $columns, string $like): void
    {
        $query->where(function (Builder $columnQuery) use ($columns, $like) {
            foreach ($columns as $column) {
                $columnQuery->orWhere($column, 'like', $like);
            }
        });
    }

    /**
     * @param  Builder<\App\Models\User>  $query
     */
    private static function applyCompactNameMatch(Builder $query, string $compactLike): void
    {
        self::applyCompactExpressionMatch(
            $query,
            self::concatColumns(['last_name', 'first_name']),
            $compactLike,
        );
        self::applyCompactExpressionMatch($query, 'COALESCE(name, \'\')', $compactLike);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function applyCompactExpressionMatch(Builder $query, string $expression, string $compactLike): void
    {
        $query->orWhereRaw(self::removeSpacesSql($expression).' LIKE ?', [$compactLike]);
    }

    private static function removeSpacesSql(string $expression): string
    {
        return "REPLACE(REPLACE({$expression}, ' ', ''), '　', '')";
    }

    /**
     * @param  list<string>  $columns
     */
    private static function concatColumns(array $columns): string
    {
        $parts = array_map(
            fn (string $column) => "COALESCE({$column}, '')",
            $columns,
        );

        if (DB::connection()->getDriverName() === 'sqlite') {
            return implode(' || ', $parts);
        }

        return 'CONCAT('.implode(', ', $parts).')';
    }

    /**
     * @param  Builder<\App\Models\EmployeeHrDetail>  $query
     */
    private static function applyPhoneDigitMatch(Builder $query, string $keyword): void
    {
        $digits = preg_replace('/\D/u', '', $keyword) ?? '';

        if ($digits === '') {
            return;
        }

        $digitLike = '%'.$digits.'%';

        foreach (['company_phone', 'phone'] as $phoneColumn) {
            $query->orWhereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$phoneColumn}, ''), '-', ''), ' ', ''), '，', ''), '、', '') LIKE ?",
                [$digitLike],
            );
        }
    }
}
