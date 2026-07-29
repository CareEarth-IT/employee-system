@php
    use App\Models\EmployeeHrDetail;
    use App\Models\User;

    $d = $hrDetail;
    $date = fn (?Illuminate\Support\Carbon $value) => $value?->format('Y-m-d');
    $roCore = ! ($access['core']['edit'] ?? false);
    $roProcedures = ! ($access['procedures']['edit'] ?? false);
    $roIt = ! ($access['it']['edit'] ?? false);
    $roItDevice = ! (($access['it']['edit'] ?? false) || ($access['it']['edit_self_device'] ?? false));
@endphp

<div class="space-y-8">
    @if ($access['core']['view'])
        <section class="space-y-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-200 pb-2">
                <h2 class="text-lg font-bold">基本情報・個人情報</h2>
                @if ($roCore)
                    <span class="text-xs text-slate-500">閲覧のみ（編集は人事部）</span>
                @endif
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm mb-1">ID</label>
                    <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">{{ $user->employee_id ?? '—' }}</p>
                </div>
                <div>
                    <label class="block text-sm mb-1">名前</label>
                    <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">{{ $user->displayName() }}</p>
                </div>
                @include('partials.form-field', ['name' => 'name_kana_fullwidth', 'label' => 'カナ（全角）', 'value' => $d->name_kana_fullwidth, 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'name_kana_halfwidth', 'label' => 'ｶﾅ（半角）', 'value' => $d->name_kana_halfwidth, 'readonly' => $roCore])
                <div>
                    <label class="block text-sm mb-1">Name（英語フル・パスポートと一致）</label>
                    <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">{{ $user->profile?->english_name ?? '—' }}</p>
                </div>
                <div>
                    <label class="block text-sm mb-1">略称（全角10文字）</label>
                    <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">{{ $user->profile?->abbreviated_name ?? '—' }}</p>
                </div>
                @include('partials.form-field', ['name' => 'affiliation_code', 'label' => '所属', 'value' => $d->affiliation_code, 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'employment_type', 'label' => '雇用形態', 'value' => $d->employment_type, 'hint' => '（例: 正社員）', 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'employment_status', 'label' => '状況', 'value' => $d->employment_status, 'hint' => '（例: 在籍）', 'readonly' => $roCore])
                <div>
                    <label class="block text-sm mb-1">入社日</label>
                    <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">{{ $user->profile?->joined_at?->format('Y/m/d') ?? '—' }}</p>
                </div>
                @include('partials.form-field', ['name' => 'resigned_at', 'label' => '退職日', 'type' => 'date', 'value' => $date($d->resigned_at), 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'last_working_day', 'label' => '最終出勤日', 'type' => 'date', 'value' => $date($d->last_working_day), 'readonly' => $roCore])
            </div>
        </section>

        <section class="space-y-4">
            <h3 class="text-base font-semibold text-slate-800">在留資格</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @include('partials.form-field', ['name' => 'residence_status', 'label' => '在留資格', 'value' => $d->residence_status, 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'residence_expires_at', 'label' => '在留期限', 'type' => 'date', 'value' => $date($d->residence_expires_at), 'readonly' => $roCore])
                @include('partials.form-field', [
                    'name' => 'residence_card_renewal_status',
                    'label' => '在留カード更新状況',
                    'type' => 'select',
                    'options' => EmployeeHrDetail::RESIDENCE_CARD_RENEWAL_STATUSES,
                    'value' => $d->residence_card_renewal_status,
                    'readonly' => $roCore,
                ])
            </div>
            @include('partials.form-field', ['name' => 'residence_renewal_memo', 'label' => '在留資格更新メモ', 'type' => 'textarea', 'rows' => 4, 'value' => $d->residence_renewal_memo, 'readonly' => $roCore])
        </section>

        <section class="space-y-4">
            <h3 class="text-base font-semibold text-slate-800">部署・役職</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @include('partials.form-field', ['name' => 'department_primary', 'label' => '部署①', 'value' => $d->department_primary, 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'section_primary', 'label' => '課/チーム①', 'value' => $d->section_primary, 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'position_primary', 'label' => '役職①', 'value' => $d->position_primary, 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'department_secondary', 'label' => '部署②', 'value' => $d->department_secondary, 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'section_secondary', 'label' => '課/チーム②', 'value' => $d->section_secondary, 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'position_secondary', 'label' => '役職②', 'value' => $d->position_secondary, 'readonly' => $roCore])
                @include('partials.form-field', [
                    'name' => 'jurisdiction',
                    'label' => '管轄',
                    'type' => 'select',
                    'options' => User::OFFICE_LOCATIONS,
                    'value' => $d->jurisdiction,
                    'readonly' => $roCore,
                ])
            </div>
        </section>

        <section class="space-y-4">
            <h3 class="text-base font-semibold text-slate-800">個人情報・連絡先</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @include('partials.form-field', ['name' => 'gender', 'label' => '性別', 'type' => 'select', 'options' => EmployeeHrDetail::GENDERS, 'value' => $d->gender, 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'birth_date', 'label' => '生年月日', 'type' => 'date', 'value' => $date($d->birth_date), 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'phone', 'label' => '電話番号', 'value' => $d->phone, 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'personal_email', 'label' => '個人アドレス', 'type' => 'email', 'value' => $d->personal_email, 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'my_number_verified', 'label' => 'マイナンバー確認済み', 'type' => 'checkbox', 'value' => $d->my_number_verified, 'readonly' => $roCore])
            </div>
            @include('partials.form-field', ['name' => 'remarks', 'label' => '備考', 'type' => 'textarea', 'rows' => 4, 'value' => $d->remarks, 'readonly' => $roCore])
        </section>
    @endif

    @if ($access['procedures']['view'])
        <section class="space-y-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-200 pb-2">
                <h2 class="text-lg font-bold">入社・退職手続き</h2>
                @if ($roProcedures)
                    <span class="text-xs text-slate-500">閲覧のみ（編集は人事課・役員）</span>
                @endif
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm mb-1">社内メール</label>
                    <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm break-all">{{ $user->email }}</p>
                </div>
            </div>
            @include('partials.form-field', ['name' => 'address_as_of_jan1', 'label' => '1月1日時点の住所', 'type' => 'textarea', 'rows' => 3, 'value' => $d->address_as_of_jan1, 'readonly' => $roProcedures])

            <h3 class="text-base font-semibold text-slate-800 pt-2">入社時手続き</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @include('partials.form-field', ['name' => 'previous_withholding_slip', 'label' => '前職の源泉徴収票', 'value' => $d->previous_withholding_slip, 'hint' => '（あり/なし＋回収）', 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'resident_tax_switch_form', 'label' => '住民税 特徴切替届', 'value' => $d->resident_tax_switch_form, 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'money_forward_setup', 'label' => 'マネーフォワード 設定', 'value' => $d->money_forward_setup, 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'rakuraku_seisan_setup', 'label' => '楽楽精算 設定', 'value' => $d->rakuraku_seisan_setup, 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'smarthr_setup', 'label' => 'SmartHR 設定', 'value' => $d->smarthr_setup, 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'business_card_onboarding', 'label' => '名刺（入社時）', 'value' => $d->business_card_onboarding, 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'health_check_received_at', 'label' => '健康診断リスト（受信日）', 'type' => 'date', 'value' => $date($d->health_check_received_at), 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'employment_insurance_number', 'label' => '雇用保険（番号）', 'value' => $d->employment_insurance_number, 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'employment_insurance_applied_at', 'label' => '雇用保険 申請日', 'type' => 'date', 'value' => $date($d->employment_insurance_applied_at), 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'health_pension_number', 'label' => '健康保険・厚生年金（番号）', 'value' => $d->health_pension_number, 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'health_pension_applied_at', 'label' => '健康保険・厚生年金 申請日', 'type' => 'date', 'value' => $date($d->health_pension_applied_at), 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'dependent_add_social_insurance', 'label' => '扶養追加（社保）', 'value' => $d->dependent_add_social_insurance, 'readonly' => $roProcedures])
            </div>

            <h3 class="text-base font-semibold text-slate-800 pt-4">退職手続き</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @include('partials.form-field', ['name' => 'resident_tax_transfer_form', 'label' => '住民税 異動届', 'value' => $d->resident_tax_transfer_form, 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'employment_insurance_withdrawal', 'label' => '雇用保険 脱退手続き', 'value' => $d->employment_insurance_withdrawal, 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'employment_insurance_withdrawal_applied_at', 'label' => '雇用保険 脱退 申請日', 'type' => 'date', 'value' => $date($d->employment_insurance_withdrawal_applied_at), 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'health_pension_withdrawal', 'label' => '健康保険・厚生年金 脱退手続き', 'value' => $d->health_pension_withdrawal, 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'health_pension_withdrawal_applied_at', 'label' => '健康保険・厚生年金 脱退 申請日', 'type' => 'date', 'value' => $date($d->health_pension_withdrawal_applied_at), 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'withholding_tax_slip', 'label' => '源泉徴収票', 'value' => $d->withholding_tax_slip, 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'separation_certificate', 'label' => '離職票', 'value' => $d->separation_certificate, 'readonly' => $roProcedures])
                @include('partials.form-field', ['name' => 'resignation_certificate', 'label' => '退職証明書', 'value' => $d->resignation_certificate, 'readonly' => $roProcedures])
            </div>
        </section>
    @endif

    @if ($access['it']['view'])
        <section class="space-y-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-200 pb-2">
                <h2 class="text-lg font-bold">IT・デバイス</h2>
                @if ($roIt && ($access['it']['edit_self_device'] ?? false))
                    <span class="text-xs text-slate-500">メーカー・型番・MACは本人編集可（その他は閲覧のみ）</span>
                @elseif ($roIt)
                    <span class="text-xs text-slate-500">閲覧のみ（編集は情シス）</span>
                @endif
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @include('partials.form-field', [
                    'name' => 'company_phone',
                    'label' => '社用の電話番号',
                    'type' => 'textarea',
                    'rows' => 2,
                    'hint' => '複数ある場合はカンマ区切り',
                    'value' => $d->company_phone,
                    'readonly' => $roIt,
                ])
                @include('partials.form-field', ['name' => 'has_pc', 'label' => 'PC', 'type' => 'checkbox', 'value' => $d->has_pc, 'readonly' => $roIt])
                @include('partials.form-field', [
                    'name' => 'pc_manufacturer',
                    'label' => 'PCメーカー',
                    'type' => 'select',
                    'options' => EmployeeHrDetail::PC_MANUFACTURERS,
                    'value' => $d->pc_manufacturer,
                    'readonly' => $roItDevice,
                ])
                @include('partials.form-field', ['name' => 'pc_model', 'label' => 'PCの型番', 'value' => $d->pc_model, 'readonly' => $roItDevice])
                @include('partials.form-field', ['name' => 'mac_address', 'label' => 'PCのMACアドレス', 'value' => $d->mac_address, 'readonly' => $roItDevice])
                @include('partials.form-field', ['name' => 'has_mobile', 'label' => 'モバイル', 'type' => 'checkbox', 'value' => $d->has_mobile, 'readonly' => $roIt])
                @include('partials.form-field', ['name' => 'mobile_manufacturer', 'label' => 'モバイル機種名', 'value' => $d->mobile_manufacturer, 'readonly' => $roItDevice])
                @include('partials.form-field', ['name' => 'setup_completed', 'label' => 'セットアップ', 'type' => 'checkbox', 'value' => $d->setup_completed, 'readonly' => $roIt])
                @include('partials.form-field', ['name' => 'device_collected', 'label' => '退職者デバイス回収', 'type' => 'checkbox', 'value' => $d->device_collected, 'readonly' => $roIt])
                @include('partials.form-field', ['name' => 'microsoft_account_removed', 'label' => 'Microsoft（退職者削除用）', 'type' => 'checkbox', 'value' => $d->microsoft_account_removed, 'readonly' => $roIt])
                @include('partials.form-field', ['name' => 'gws_account_removed', 'label' => 'GWS（退職者削除用）', 'type' => 'checkbox', 'value' => $d->gws_account_removed, 'readonly' => $roIt])
                @include('partials.form-field', ['name' => 'slack_account_removed', 'label' => 'slack（退職者削除用）', 'type' => 'checkbox', 'value' => $d->slack_account_removed, 'readonly' => $roIt])
            </div>
        </section>
    @endif
</div>
