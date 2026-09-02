@php
    use App\Models\EmployeeHrDetail;
    use App\Models\User;
    use App\Support\NationalityOptions;

    $d = $hrDetail;
    $date = fn (?Illuminate\Support\Carbon $value) => $value?->format('Y-m-d');
    $roCore = ! ($access['core']['edit'] ?? false);
    $roIt = ! ($access['it']['edit'] ?? false);
    $roItDevice = ! (($access['it']['edit'] ?? false) || ($access['it']['edit_self_device'] ?? false));
    $nationalityValue = NationalityOptions::toDisplayName($user->profile?->nationality) ?? ($user->profile?->nationality ?? '');
    $affiliationSelectOptions = User::affiliationSelectOptions($d->affiliation_code);
    $employmentTypeOptions = User::employmentTypeOptions($d->employment_type);
    $employmentStatusOptions = User::employmentStatusOptions($d->employment_status);
    $positionPrimaryOptions = User::registryPositionOptions($d->position_primary);
    $positionSecondaryOptions = User::registryPositionOptions($d->position_secondary);
    $primaryOrgValues = \App\Support\HrDetailOrgFormState::assignmentValues($d, 'primary');
    $secondaryOrgValues = \App\Support\HrDetailOrgFormState::assignmentValues($d, 'secondary');
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
                <div>
                    <label class="block text-sm mb-1">Name</label>
                    <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">{{ $user->profile?->english_name ?? '—' }}</p>
                </div>
                <div>
                    <label class="block text-sm mb-1">社用メール</label>
                    <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm break-all">{{ $user->email }}</p>
                </div>
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
                    'value' => $d->affiliation_code,
                    'readonly' => $roCore,
                ])
                @include('partials.form-field', [
                    'name' => 'employment_type',
                    'label' => '雇用形態',
                    'type' => 'select',
                    'options' => $employmentTypeOptions,
                    'value' => $d->employment_type,
                    'hint' => '（例: 正社員）',
                    'readonly' => $roCore,
                ])
                <div>
                    <label for="nationality" class="block text-sm mb-1">国籍</label>
                    @if ($roCore)
                        <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">{{ $nationalityValue !== '' ? $nationalityValue : '—' }}</p>
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
                    <label class="block text-sm mb-1">入社日</label>
                    <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">{{ $user->profile?->joined_at?->format('Y/m/d') ?? '—' }}</p>
                </div>
                @include('partials.form-field', ['name' => 'resigned_at', 'label' => '退職日', 'type' => 'date', 'value' => $date($d->resigned_at), 'readonly' => $roCore])
                @include('partials.form-field', ['name' => 'last_working_day', 'label' => '最終出勤日', 'type' => 'date', 'value' => $date($d->last_working_day), 'readonly' => $roCore])
            </div>
        </section>

        <section class="space-y-4">
            <h3 class="text-base font-semibold text-slate-800">部署・役職</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @include('partials.hr-detail-org-assignment', [
                    'hrDetail' => $d,
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
                ],
            ])
        @endunless

        <section class="space-y-4">
            <h3 class="text-base font-semibold text-slate-800">個人情報</h3>
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
