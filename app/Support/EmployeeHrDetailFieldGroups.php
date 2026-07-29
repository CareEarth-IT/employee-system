<?php

namespace App\Support;

class EmployeeHrDetailFieldGroups
{
    /** @var list<string> */
    public const CORE = [
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
        'personal_email',
        'my_number_verified',
        'remarks',
    ];

    /** @var list<string> */
    public const PROCEDURES = [
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
        'resident_tax_transfer_form',
        'employment_insurance_withdrawal',
        'employment_insurance_withdrawal_applied_at',
        'health_pension_withdrawal',
        'health_pension_withdrawal_applied_at',
        'withholding_tax_slip',
        'separation_certificate',
        'resignation_certificate',
    ];

    /** @var list<string> */
    public const IT = [
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
    ];

    /** 本人も編集可能な IT・デバイス項目 */
    /** @var list<string> */
    public const IT_SELF_EDITABLE = [
        'pc_manufacturer',
        'pc_model',
        'mac_address',
        'mobile_manufacturer',
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(array_unique(array_merge(self::CORE, self::PROCEDURES, self::IT)));
    }
}
