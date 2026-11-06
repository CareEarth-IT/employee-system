@php
    use App\Models\EmployeeHrDetail;
    use App\Models\User;
    use App\Support\NationalityOptions;

    $d = $hrDetail;
    $date = fn (?Illuminate\Support\Carbon $value) => $value?->format('Y-m-d');
    $roCore = ! ($access['core']['edit'] ?? false);
    $roIt = ! ($access['it']['edit'] ?? false);
    $roItDevice = ! (($access['it']['edit'] ?? false) || ($access['it']['edit_self_device'] ?? false));
    $canEditIdentity = (bool) ($access['canEditIdentity'] ?? false);
    $nationalityValue = NationalityOptions::toDisplayName($user->profile?->nationality) ?? ($user->profile?->nationality ?? '');
    $affiliationSelectOptions = User::companyAffiliationSelectOptions($d->affiliation_code);
    $affiliationDisplayName = User::affiliationDisplayName($d->affiliation_code);
    $employmentTypeOptions = User::employmentTypeOptions($d->employment_type);
    $employmentStatusOptions = User::employmentStatusOptions($d->employment_status);
    $positionPrimaryOptions = User::registryPositionOptions($d->position_primary);
    $positionSecondaryOptions = User::registryPositionOptions($d->position_secondary);
    $primaryOrgValues = \App\Support\HrDetailOrgFormState::assignmentValues($d, 'primary', $user);
    $secondaryOrgValues = \App\Support\HrDetailOrgFormState::assignmentValues($d, 'secondary', $user);
@endphp

<div class="space-y-8">
    @if ($access['core']['view'])
        <section class="space-y-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-200 pb-2">
                <h2 class="text-xl font-bold">基本情報・個人情報</h2>
                @if ($roCore)
                    <span class="text-sm text-slate-500">閲覧のみ（編集は人事部）</span>
                @endif
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @if ($canEditIdentity)
                    <div>
                        <label for="employee_id" class="mb-1 block text-base">ID</label>
                        <input
                            id="employee_id"
                            name="employee_id"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            maxlength="5"
                            value="{{ old('employee_id', $user->employee_id) }}"
                            class="w-full rounded border border-slate-300 px-3 py-2 text-base"
                            oninput="this.value = this.value.replace(/\D/g, '').slice(0, 5)"
                        >
                        <p class="mt-1 text-sm text-slate-500">1〜5桁の数字。未入力可。</p>
                        @include('partials.field-error', ['field' => 'employee_id'])
                    </div>
                    <div>
                        <label for="last_name" class="mb-1 block text-base">姓 <span class="text-red-600">*</span></label>
                        <input id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" required class="w-full rounded border border-slate-300 px-3 py-2 text-base" autocomplete="family-name">
                        @include('partials.field-error', ['field' => 'last_name'])
                    </div>
                    <div>
                        <label for="first_name" class="mb-1 block text-base">名 <span class="text-red-600">*</span></label>
                        <input id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" required class="w-full rounded border border-slate-300 px-3 py-2 text-base" autocomplete="given-name">
                        @include('partials.field-error', ['field' => 'first_name'])
                    </div>
                    <div>
                        <label for="email" class="mb-1 block text-base">社用メール</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" class="w-full break-all rounded border border-slate-300 px-3 py-2 text-base">
                        <p class="mt-1 text-sm text-slate-500">未入力可。</p>
                        @include('partials.field-error', ['field' => 'email'])
                    </div>
                @else
                    <div>
                        <label class="mb-1 block text-base">ID</label>
                        <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-base">{{ $user->employee_id ?? '—' }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-base">名前</label>
                        <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-base">{{ $user->displayName() }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-base">社用メール</label>
                        <p class="break-all rounded border border-slate-200 bg-slate-50 px-3 py-2 text-base">{{ $user->email ?: '—' }}</p>
                    </div>
                @endif
                @include('partials.form-field', [
                    'name' => 'english_name',
                    'label' => 'Name',
                    'hint' => '（パスポート表記）',
                    'value' => $user->profile?->english_name,
                    'readonly' => $roCore && ! $canEditIdentity,
                ])
                @include('partials.form-field', [
                    'name' => 'gmail_address',
                    'label' => 'Gmailアドレス',
                    'type' => 'email',
                    'value' => $d->gmail_address,
                    'readonly' => $roCore,
                ])
                @include('partials.form-field', [
                    'name' => 'affiliation_code',
                    'label' => '所属',
                    'type' => 'select',
                    'options' => $affiliationSelectOptions,
                    'value' => $affiliationDisplayName,
                    'readonly' => $roCore,
                ])
                <div>
                    @include('partials.form-field', [
                        'name' => 'employment_type',
                        'label' => '雇用形態',
                        'type' => 'select',
                        'options' => $employmentTypeOptions,
                        'value' => $d->employment_type,
                        'hint' => '（例: 正社員）',
                        'readonly' => $roCore,
                    ])
                    <div id="hr-detail-social-insurance-field" class="mt-2 {{ old('employment_type', $d->employment_type) === 'アルバイト' ? '' : 'hidden' }}">
                        @if ($roCore)
                            <p class="text-base">{{ $d->has_social_insurance ? '社会保険あり' : '社会保険' }}</p>
                        @else
                            <input type="hidden" name="has_social_insurance" value="0">
                            <label class="inline-flex cursor-pointer items-center gap-2 text-base">
                                <input
                                    id="has_social_insurance"
                                    type="checkbox"
                                    name="has_social_insurance"
                                    value="1"
                                    @checked(old('has_social_insurance', $d->has_social_insurance))
                                    class="rounded border-slate-300"
                                >
                                <span id="has_social_insurance_label">社会保険{{ old('has_social_insurance', $d->has_social_insurance) ? 'あり' : '' }}</span>
                            </label>
                            @include('partials.field-error', ['field' => 'has_social_insurance'])
                        @endif
                    </div>
                </div>
                <div>
                    <label for="nationality" class="mb-1 block text-base">国籍</label>
                    @if ($roCore)
                        <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-base">{{ $nationalityValue !== '' ? $nationalityValue : '—' }}</p>
                    @else
                        @include('partials.nationality-select', [
                            'selected' => old('nationality', $nationalityValue),
                        ])
                        @include('partials.field-error', ['field' => 'nationality'])
                    @endif
                </div>
                @include('partials.form-field', [
                    'name' => 'employment_status',
                    'label' => '状況',
                    'type' => 'select',
                    'options' => $employmentStatusOptions,
                    'value' => $d->employment_status,
                    'hint' => '（在籍・休職・退職）',
                    'readonly' => $roCore,
                ])
                <div>
                    <label class="mb-1 block text-base">入社日</label>
                    @if ($roCore)
                        <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-base">{{ $user->profile?->joined_at?->format('Y/m/d') ?? '—' }}</p>
                    @else
                        @include('partials.date-field', [
                            'name' => 'joined_at',
                            'id' => 'joined_at',
                            'value' => old('joined_at', $user->profile?->joined_at),
                        ])
                        <p class="mt-1 text-sm text-slate-500">不明な場合は空欄のままで構いません。</p>
                        @include('partials.field-error', ['field' => 'joined_at'])
                    @endif
                </div>
                @include('partials.form-field', ['name' => 'resigned_at', 'label' => '退職日', 'type' => 'date', 'value' => $date($d->resigned_at), 'hint' => '（不明な場合は空欄）', 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'last_working_day', 'label' => '最終出勤日', 'type' => 'date', 'value' => $date($d->last_working_day), 'readonly' => $roCore])
            </div>
        </section>

        <section class="space-y-4">
            <h3 class="text-lg font-semibold text-slate-800">部署・役職</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @include('partials.form-field', [
                    'name' => 'jurisdiction',
                    'label' => '管轄',
                    'type' => 'select',
                    'options' => User::OFFICE_LOCATIONS,
                    'value' => $d->jurisdiction,
                    'readonly' => $roCore,
                ])
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @include('partials.hr-detail-org-assignment', [
                    'hrDetail' => $d,
                    'user' => $user,
                    'suffix' => 'primary',
                    'readonly' => $roCore,
                ])
                @include('partials.form-field', [
                    'name' => 'position_primary',
                    'label' => '役職①',
                    'type' => 'select',
                    'options' => $positionPrimaryOptions,
                    'value' => $d->position_primary,
                    'readonly' => $roCore,
                ])
                @include('partials.hr-detail-org-assignment', [
                    'hrDetail' => $d,
                    'user' => $user,
                    'suffix' => 'secondary',
                    'readonly' => $roCore,
                ])
                @include('partials.form-field', [
                    'name' => 'position_secondary',
                    'label' => '役職②',
                    'type' => 'select',
                    'options' => $positionSecondaryOptions,
                    'value' => $d->position_secondary,
                    'readonly' => $roCore,
                ])
            </div>
            @unless ($roCore)
                @php
                    $extraOrgRows = \App\Support\HrDetailOrgFormState::extraAssignmentRows($user);
                    $positionOptions = \App\Support\RegistryPositionOptions::OPTIONS;
                @endphp
                <input type="hidden" name="org_extra_present" value="1">
                <div id="hr-detail-org-extra-list" class="space-y-4">
                    @foreach ($extraOrgRows as $extraIndex => $extraRow)
                        @include('partials.hr-detail-org-extra-row', [
                            'index' => $extraIndex,
                            'row' => $extraRow,
                            'positionOptions' => $positionOptions,
                            'location' => $d->jurisdiction,
                        ])
                    @endforeach
                </div>
                <button type="button" id="hr-detail-org-add" class="rounded border border-slate-400 px-3 py-1.5 text-sm hover:bg-slate-50">
                    部署を追加
                </button>
                <template id="hr-detail-org-extra-template">
                    @include('partials.hr-detail-org-extra-row', [
                        'index' => '__INDEX__',
                        'mark' => '__MARK__',
                        'row' => [
                            'affiliation_id' => '',
                            'department' => '',
                            'section' => '',
                            'team' => '',
                            'position' => '',
                            'location' => '',
                        ],
                        'positionOptions' => $positionOptions,
                        'location' => $d->jurisdiction,
                    ])
                </template>
            @endunless
        </section>

        @unless ($roCore)
            @include('partials.hr-detail-org-assignment-script', [
                'locationFieldId' => 'jurisdiction',
                'blocks' => [
                    [
                        'suffix' => 'primary',
                        'departmentId' => 'department_primary',
                        'sectionId' => 'section_primary',
                        'teamId' => 'team_primary',
                        'sectionHintId' => 'hr-detail-section-hint-primary',
                        'teamHintId' => 'hr-detail-team-hint-primary',
                        'teamFieldId' => 'hr-detail-team-field-primary',
                        'initialSection' => old('section_primary', $primaryOrgValues['section']),
                        'initialTeam' => old('team_primary', $primaryOrgValues['team']),
                    ],
                    [
                        'suffix' => 'secondary',
                        'departmentId' => 'department_secondary',
                        'sectionId' => 'section_secondary',
                        'teamId' => 'team_secondary',
                        'sectionHintId' => 'hr-detail-section-hint-secondary',
                        'teamHintId' => 'hr-detail-team-hint-secondary',
                        'teamFieldId' => 'hr-detail-team-field-secondary',
                        'initialSection' => old('section_secondary', $secondaryOrgValues['section']),
                        'initialTeam' => old('team_secondary', $secondaryOrgValues['team']),
                    ],
                    ...collect($extraOrgRows ?? [])->map(fn (array $extraRow, int $extraIndex): array => [
                        'suffix' => 'extra_'.$extraIndex,
                        'departmentId' => 'org_extra_'.$extraIndex.'_department',
                        'sectionId' => 'org_extra_'.$extraIndex.'_section',
                        'teamId' => 'org_extra_'.$extraIndex.'_team',
                        'sectionHintId' => 'hr-detail-section-hint-extra-'.$extraIndex,
                        'teamHintId' => 'hr-detail-team-hint-extra-'.$extraIndex,
                        'teamFieldId' => 'hr-detail-team-field-extra-'.$extraIndex,
                        'initialSection' => $extraRow['section'] ?? '',
                        'initialTeam' => $extraRow['team'] ?? '',
                    ])->all(),
                ],
            ])
        @endunless

        <section class="space-y-4">
            <h3 class="text-lg font-semibold text-slate-800">個人情報</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @include('partials.form-field', ['name' => 'gender', 'label' => '性別', 'type' => 'select', 'options' => EmployeeHrDetail::GENDERS, 'value' => $d->gender, 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'birth_date', 'label' => '生年月日', 'type' => 'date', 'value' => $date($d->birth_date), 'readonly' => $roCore])
                @include('partials.form-field', [
                    'name' => 'company_phone',
                    'label' => '社用電話番号',
                    'type' => 'textarea',
                    'rows' => 2,
                    'hint' => '複数ある場合はカンマ区切り',
                    'value' => $d->company_phone,
                    'readonly' => $roCore,
                ])
            </div>
            @include('partials.form-field', ['name' => 'remarks', 'label' => '備考', 'type' => 'textarea', 'rows' => 4, 'value' => $d->remarks, 'readonly' => $roCore])
        </section>
    @endif

    @if ($access['it']['view'])
        @include('profile.hr-detail._it-section', [
            'hrDetail' => $hrDetail,
            'access' => $access,
        ])
    @endif
</div>

@unless ($roCore)
<script>
(() => {
    const employmentType = document.getElementById('employment_type');
    const socialInsuranceField = document.getElementById('hr-detail-social-insurance-field');
    const socialInsuranceCheckbox = document.getElementById('has_social_insurance');
    const socialInsuranceLabel = document.getElementById('has_social_insurance_label');

    if (!employmentType || !socialInsuranceField) {
        return;
    }

    const syncSocialInsuranceLabel = () => {
        if (!socialInsuranceLabel || !socialInsuranceCheckbox) {
            return;
        }

        socialInsuranceLabel.textContent = socialInsuranceCheckbox.checked ? '社会保険あり' : '社会保険';
    };

    const syncSocialInsurance = () => {
        const isPartTime = employmentType.value === 'アルバイト';
        socialInsuranceField.classList.toggle('hidden', !isPartTime);
        if (socialInsuranceCheckbox && !isPartTime) {
            socialInsuranceCheckbox.checked = false;
        }
        syncSocialInsuranceLabel();
    };

    employmentType.addEventListener('change', syncSocialInsurance);
    socialInsuranceCheckbox?.addEventListener('change', syncSocialInsuranceLabel);
    syncSocialInsurance();
})();
</script>
@endunless
