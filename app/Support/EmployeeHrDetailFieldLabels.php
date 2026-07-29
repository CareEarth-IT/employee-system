<?php

namespace App\Support;

class EmployeeHrDetailFieldLabels
{
    /** @var array<string, string> */
    public const LABELS = [
        'primary_id' => 'Primary ID',
        'employee_id' => '社員ID',
        'name' => '名前',
        'email' => '社内メール',
        'english_name' => 'Name（英語）',
        'abbreviated_name' => '略称',
        'joined_at' => '入社日',
        'name_kana_fullwidth' => 'カナ（全角）',
        'name_kana_halfwidth' => 'ｶﾅ（半角）',
        'affiliation_code' => '所属',
        'employment_type' => '雇用形態',
        'employment_status' => '状況',
        'resigned_at' => '退職日',
        'last_working_day' => '最終出勤日',
        'residence_status' => '在留資格',
        'residence_expires_at' => '在留期限',
        'residence_renewal_memo' => '在留資格更新メモ',
        'residence_card_renewal_status' => '在留カード更新状況',
        'department_primary' => '部署①',
        'section_primary' => '課/チーム①',
        'position_primary' => '役職①',
        'department_secondary' => '部署②',
        'section_secondary' => '課/チーム②',
        'position_secondary' => '役職②',
        'jurisdiction' => '管轄',
        'gender' => '性別',
        'birth_date' => '生年月日',
        'phone' => '電話番号',
        'personal_email' => '個人アドレス',
        'my_number_verified' => 'マイナンバー確認済み',
        'remarks' => '備考',
        'address_as_of_jan1' => '1月1日時点の住所',
        'previous_withholding_slip' => '前職の源泉徴収票',
        'resident_tax_switch_form' => '住民税 特徴切替届',
        'money_forward_setup' => 'マネーフォワード 設定',
        'rakuraku_seisan_setup' => '楽楽精算 設定',
        'smarthr_setup' => 'SmartHR 設定',
        'business_card_onboarding' => '名刺（入社時）',
        'health_check_received_at' => '健康診断リスト（受信日）',
        'employment_insurance_number' => '雇用保険（番号）',
        'employment_insurance_applied_at' => '雇用保険 申請日',
        'health_pension_number' => '健康保険・厚生年金（番号）',
        'health_pension_applied_at' => '健康保険・厚生年金 申請日',
        'dependent_add_social_insurance' => '扶養追加（社保）',
        'resident_tax_transfer_form' => '住民税 異動届',
        'employment_insurance_withdrawal' => '雇用保険 脱退手続き',
        'employment_insurance_withdrawal_applied_at' => '雇用保険 脱退 申請日',
        'health_pension_withdrawal' => '健康保険・厚生年金 脱退手続き',
        'health_pension_withdrawal_applied_at' => '健康保険・厚生年金 脱退 申請日',
        'withholding_tax_slip' => '源泉徴収票',
        'separation_certificate' => '離職票',
        'resignation_certificate' => '退職証明書',
        'has_pc' => 'PC',
        'pc_manufacturer' => 'PCメーカー',
        'pc_model' => 'PCの型番',
        'mac_address' => 'PCのMACアドレス',
        'has_mobile' => 'モバイル',
        'mobile_manufacturer' => 'モバイル機種名',
        'setup_completed' => 'セットアップ',
        'device_collected' => '退職者デバイス回収',
        'microsoft_account_removed' => 'Microsoft（退職者削除用）',
        'gws_account_removed' => 'GWS（退職者削除用）',
        'slack_account_removed' => 'slack（退職者削除用）',
        'company_phone' => '社用の電話番号',
    ];

    /** @var list<string> */
    public const META_CORE = [
        'primary_id',
        'employee_id',
        'name',
        'english_name',
        'abbreviated_name',
        'joined_at',
    ];

    /** @var list<string> */
    public const META_PROCEDURES = [
        'email',
    ];

    /** @var list<string> */
    public const META = [
        ...self::META_CORE,
        ...self::META_PROCEDURES,
    ];

    public static function label(string $field): string
    {
        return self::LABELS[$field] ?? $field;
    }
}
