<?php

namespace App\Services;

use App\Mail\SocialInsuranceCompanyThresholdReached;
use App\Models\AffiliationHistory;
use App\Models\SocialInsuranceThresholdNotification;
use App\Models\User;
use App\Support\SocialInsuranceCompanyStats;
use Illuminate\Support\Facades\Mail;

class SocialInsuranceThresholdNotifier
{
    public const HR_NOTIFICATION_EMAIL = 'recruit-hr@careearth.info';

    /**
     * 各所属会社の社会保険あり人数を確認し、閾値到達時に人事・総務へメールする。
     */
    public function notifyReachedCompanies(): void
    {
        $threshold = SocialInsuranceCompanyStats::THRESHOLD;

        foreach (SocialInsuranceCompanyStats::countsByCompany() as $row) {
            $company = $row['company'];
            $count = $row['count'];

            if ($count < $threshold) {
                SocialInsuranceThresholdNotification::query()
                    ->where('company', $company)
                    ->where('threshold', $threshold)
                    ->delete();

                continue;
            }

            $alreadyNotified = SocialInsuranceThresholdNotification::query()
                ->where('company', $company)
                ->where('threshold', $threshold)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            $recipients = $this->recipientEmails();

            if ($recipients === []) {
                continue;
            }

            Mail::to($recipients)->send(new SocialInsuranceCompanyThresholdReached(
                company: $company,
                count: $count,
                threshold: $threshold,
            ));

            SocialInsuranceThresholdNotification::query()->create([
                'company' => $company,
                'threshold' => $threshold,
                'notified_at' => now(),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    public function recipientEmails(): array
    {
        $emails = [self::HR_NOTIFICATION_EMAIL];

        $today = now()->toDateString();

        $generalAffairsEmails = User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('affiliationHistories', function ($query) use ($today) {
                $query
                    ->where('enrollment_status', AffiliationHistory::STATUS_ENROLLED)
                    ->where(function ($active) use ($today) {
                        $active->whereNull('end_date')
                            ->orWhere('end_date', '>=', $today);
                    })
                    ->where('section', 'like', '%'.User::GENERAL_AFFAIRS_SECTION_KEYWORD.'%');
            })
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->all();

        return array_values(array_unique([
            ...$emails,
            ...$generalAffairsEmails,
        ]));
    }
}
