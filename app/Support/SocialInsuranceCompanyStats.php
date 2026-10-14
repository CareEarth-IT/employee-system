<?php

namespace App\Support;

use App\Models\AffiliationHistory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SocialInsuranceCompanyStats
{
    public const THRESHOLD = 30;

    /** @var list<string> 表の列順（所属コード） */
    public const COLUMN_CODES = ['CE', 'GT', 'EM', 'ME', 'CEVN'];

    /** @var array<string, string> 所属コード → バッジ色クラス */
    public const CODE_BADGE_CLASSES = [
        'CE' => 'bg-sky-100 text-sky-800',
        'GT' => 'bg-rose-100 text-rose-800',
        'EM' => 'bg-emerald-100 text-emerald-800',
        'ME' => 'bg-amber-100 text-amber-900',
        'CEVN' => 'bg-violet-100 text-violet-800',
    ];

    /**
     * 社会保険ありの在籍社員数を所属会社ごとに集計する。
     *
     * @return list<array{company: string, code: string, count: int, threshold: int, reached: bool, badge_class: string}>
     */
    public static function countsByCompany(): array
    {
        $counts = self::rawCountsByCompany();
        $rows = [];

        foreach (self::COLUMN_CODES as $code) {
            $company = User::AFFILIATION_CODE_TO_COMPANY[$code] ?? $code;
            $count = $counts->get($company, 0);
            $rows[] = [
                'company' => $company,
                'code' => $code,
                'count' => $count,
                'threshold' => self::THRESHOLD,
                'reached' => $count >= self::THRESHOLD,
                'badge_class' => self::CODE_BADGE_CLASSES[$code] ?? 'bg-slate-100 text-slate-800',
            ];
        }

        foreach ($counts as $company => $count) {
            if (in_array($company, User::COMPANY_NAMES, true)) {
                continue;
            }

            $rows[] = [
                'company' => (string) $company,
                'code' => (string) $company,
                'count' => $count,
                'threshold' => self::THRESHOLD,
                'reached' => $count >= self::THRESHOLD,
                'badge_class' => 'bg-slate-100 text-slate-800',
            ];
        }

        return $rows;
    }

    public static function countForCompany(string $company): int
    {
        return self::rawCountsByCompany()->get($company, 0);
    }

    /**
     * @return Collection<string, int>
     */
    private static function rawCountsByCompany(): Collection
    {
        $today = now()->toDateString();

        return DB::table('users')
            ->join('employee_hr_details', 'employee_hr_details.user_id', '=', 'users.id')
            ->join('affiliation_histories', 'affiliation_histories.user_id', '=', 'users.id')
            ->where('employee_hr_details.has_social_insurance', true)
            ->where('affiliation_histories.enrollment_status', AffiliationHistory::STATUS_ENROLLED)
            ->where(function ($query) use ($today) {
                $query->whereNull('affiliation_histories.end_date')
                    ->orWhere('affiliation_histories.end_date', '>=', $today);
            })
            ->whereNotNull('affiliation_histories.company')
            ->where('affiliation_histories.company', '!=', '')
            ->groupBy('affiliation_histories.company')
            ->selectRaw('affiliation_histories.company as company, COUNT(DISTINCT users.id) as aggregate')
            ->pluck('aggregate', 'company')
            ->map(fn ($count) => (int) $count);
    }
}
