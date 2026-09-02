<?php

namespace App\Models;

use App\Support\CompanyPhone;
use App\Support\EmploymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeHrDetail extends Model
{
    /** @var list<string> */
    public const GENDERS = ['男', '女', 'その他', '未回答'];

    /** @var list<string> */
    public const RESIDENCE_CARD_RENEWAL_STATUSES = [
        '更新手続き開始',
        '社労士手続き中',
        '完了',
    ];

    /** @var list<string> */
    public const PC_MANUFACTURERS = [
        'Dynabook',
        'HP',
        'mouse',
        'CB',
    ];

    protected $fillable = [
        'user_id',
        'name_kana_fullwidth',
        'name_kana_halfwidth',
        'affiliation_code',
        'employment_type',
        'employment_status',
        'resigned_at',
        'last_working_day',
        'residence_status',
        'residence_expires_at',
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
        'birth_date',
        'phone',
        'primary_id',
        'personal_email',
        'gmail_address',
        'my_number_verified',
        'remarks',
        'address_as_of_jan1',
        'previous_withholding_slip',
        'resident_tax_switch_form',
        'money_forward_setup',
        'rakuraku_seisan_setup',
        'smarthr_setup',
        'business_card_onboarding',
        'health_check_received_at',
        'employment_insurance_number',
        'employment_insurance_applied_at',
        'health_pension_number',
        'health_pension_applied_at',
        'dependent_add_social_insurance',
        'has_pc',
        'pc_manufacturer',
        'pc_model',
        'mac_address',
        'has_mobile',
        'mobile_manufacturer',
        'setup_completed',
        'device_collected',
        'microsoft_account_removed',
        'gws_account_removed',
        'slack_account_removed',
        'company_phone',
        'resident_tax_transfer_form',
        'employment_insurance_withdrawal',
        'employment_insurance_withdrawal_applied_at',
        'health_pension_withdrawal',
        'health_pension_withdrawal_applied_at',
        'withholding_tax_slip',
        'separation_certificate',
        'resignation_certificate',
    ];

    protected function casts(): array
    {
        return [
            'resigned_at' => 'date',
            'last_working_day' => 'date',
            'residence_expires_at' => 'date',
            'birth_date' => 'date',
            'my_number_verified' => 'boolean',
            'health_check_received_at' => 'date',
            'employment_insurance_applied_at' => 'date',
            'health_pension_applied_at' => 'date',
            'has_pc' => 'boolean',
            'has_mobile' => 'boolean',
            'setup_completed' => 'boolean',
            'device_collected' => 'boolean',
            'microsoft_account_removed' => 'boolean',
            'gws_account_removed' => 'boolean',
            'slack_account_removed' => 'boolean',
            'employment_insurance_withdrawal_applied_at' => 'date',
            'health_pension_withdrawal_applied_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return list<string>
     */
    public function companyPhoneList(): array
    {
        return CompanyPhone::parse($this->company_phone);
    }

    public static function bootstrapForUser(User $user): self
    {
        $affiliation = $user->currentAffiliation();
        $profile = $user->profile;
        $orgPrimary = \App\Support\RegistryOrgAssignment::hrDetailPrimaryFromAffiliation(
            $affiliation?->department,
            $affiliation?->location,
            $affiliation?->section,
        );

        $detail = self::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name_kana_fullwidth' => $profile?->name_kana,
                'employment_status' => EmploymentStatus::normalize($affiliation?->enrollment_status),
                'department_primary' => $orgPrimary['department_primary'],
                'section_primary' => $orgPrimary['section_primary'],
                'position_primary' => $affiliation?->position,
                'jurisdiction' => $affiliation?->location,
            ],
        );

        if (! $detail->primary_id) {
            $detail->update(['primary_id' => self::generatePrimaryId($user)]);
        }

        return $detail->fresh();
    }

    public static function syncPrimaryOrgFromAffiliation(User $user, ?AffiliationHistory $affiliation = null): void
    {
        $affiliation ??= $user->currentAffiliation();

        if ($affiliation === null || ! $affiliation->isEnrolled()) {
            return;
        }

        $current = $user->fresh(['affiliationHistories'])?->currentAffiliation();

        if ($current?->id !== $affiliation->id) {
            return;
        }

        self::query()->updateOrCreate(
            ['user_id' => $user->id],
            \App\Support\RegistryOrgAssignment::hrDetailPrimaryFromAffiliation(
                $affiliation->department,
                $affiliation->location,
                $affiliation->section,
            ),
        );
    }

    /**
     * @return array{
     *     changed: bool,
     *     current: array{department_primary: ?string, section_primary: ?string},
     *     target: array{department_primary: ?string, section_primary: ?string},
     * }|null
     */
    public static function primaryOrgSyncPlan(User $user): ?array
    {
        $affiliation = $user->currentAffiliation();

        if ($affiliation === null || ! $affiliation->isEnrolled()) {
            return null;
        }

        $target = \App\Support\RegistryOrgAssignment::hrDetailPrimaryFromAffiliation(
            $affiliation->department,
            $affiliation->location,
            $affiliation->section,
        );

        $detail = $user->hrDetail;
        $current = [
            'department_primary' => $detail?->department_primary,
            'section_primary' => $detail?->section_primary,
        ];

        return [
            'changed' => self::primaryOrgValuesDiffer($current, $target),
            'current' => $current,
            'target' => $target,
        ];
    }

    /**
     * @param  array{department_primary: ?string, section_primary: ?string}  $current
     * @param  array{department_primary: ?string, section_primary: ?string}  $target
     */
    private static function primaryOrgValuesDiffer(array $current, array $target): bool
    {
        foreach (['department_primary', 'section_primary'] as $field) {
            if (self::normalizePrimaryOrgValue($current[$field]) !== self::normalizePrimaryOrgValue($target[$field])) {
                return true;
            }
        }

        return false;
    }

    private static function normalizePrimaryOrgValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    public static function generatePrimaryId(User $user): string
    {
        return sprintf('P%06d', $user->id);
    }
}
