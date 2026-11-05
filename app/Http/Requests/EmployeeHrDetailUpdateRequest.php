<?php

namespace App\Http\Requests;

use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\EmployeeHrDetailAccess;
use App\Support\CompanyPhone;
use App\Support\DateInput;
use App\Support\EmployeeIdRules;
use App\Support\HrDetailOrgFormState;
use App\Support\NationalityOptions;
use App\Support\RegistryDepartmentOptions;
use App\Support\RegistrySectionByAssignment;
use App\Support\RegistryTeamByAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeHrDetailUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user') ?? auth()->user();
        $viewer = auth()->user();

        return $target instanceof User
            && $viewer !== null
            && (
                EmployeeHrDetailAccess::canUpdateAny($viewer, $target)
                || $viewer->canEditEmployeeIdentity($target)
            );
    }

    protected function prepareForValidation(): void
    {
        foreach ($this->booleanFields() as $field) {
            if (in_array($field, $this->editableFields(), true)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        if (in_array('company_phone', $this->editableFields(), true) && $this->has('company_phone')) {
            $this->merge([
                'company_phone' => CompanyPhone::normalize($this->input('company_phone')),
            ]);
        }

        if (in_array('nationality', $this->editableFields(), true) && $this->exists('nationality')) {
            $this->merge([
                'nationality' => NationalityOptions::toDisplayName($this->input('nationality')),
            ]);
        }

        foreach (['joined_at', 'resigned_at', 'last_working_day', 'birth_date'] as $field) {
            if ($this->exists($field)) {
                $this->merge([
                    $field => DateInput::normalize($this->input($field)),
                ]);
            }
        }

        foreach (['section_primary', 'section_secondary', 'team_primary', 'team_secondary'] as $field) {
            if ($this->has($field) && trim((string) $this->input($field)) === '') {
                $this->merge([$field => null]);
            }
        }

        $extraRows = $this->input('org_extra');
        if (is_array($extraRows)) {
            $normalizedRows = [];
            foreach ($extraRows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach (['department', 'section', 'team', 'position'] as $key) {
                    $text = trim((string) ($row[$key] ?? ''));
                    $row[$key] = $text !== '' ? $text : null;
                }
                if ($row['department'] !== null) {
                    $row['department'] = RegistryDepartmentOptions::normalizeDepartment($row['department']);
                }
                $affiliationId = trim((string) ($row['affiliation_id'] ?? ''));
                $row['affiliation_id'] = $affiliationId !== '' ? $affiliationId : null;
                $normalizedRows[] = $row;
            }
            $this->merge(['org_extra' => $normalizedRows]);
        }

        foreach (['department_primary', 'department_secondary'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => \App\Support\RegistryDepartmentOptions::normalizeDepartment(
                        (string) $this->input($field),
                    ),
                ]);
            }
        }

        if ($this->viewerCanEditIdentity()) {
            if ($this->exists('email')) {
                $email = strtolower(trim((string) $this->input('email', '')));
                $this->merge(['email' => $email !== '' ? $email : null]);
            }

            if ($this->exists('employee_id')) {
                $employeeId = preg_replace('/\D/', '', (string) $this->input('employee_id', '')) ?? '';
                $this->merge(['employee_id' => $employeeId !== '' ? $employeeId : null]);
            }

            foreach (['last_name', 'first_name'] as $field) {
                if ($this->exists($field)) {
                    $this->merge([$field => trim((string) $this->input($field, ''))]);
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allRules = $this->allFieldRules();
        $editable = array_flip($this->editableFields());
        $rules = array_intersect_key($allRules, $editable);

        if (isset($editable['section_primary'])) {
            $rules['team_primary'] = $allRules['team_primary'];
        }

        if (isset($editable['section_secondary'])) {
            $rules['team_secondary'] = $allRules['team_secondary'];
        }

        if (isset($editable['department_primary'])) {
            $target = $this->route('user');
            $hrDetail = $target instanceof User ? $target->hrDetail : null;
            $rules['org_extra_present'] = ['nullable', 'boolean'];
            $rules['org_extra'] = ['nullable', 'array'];
            $rules['org_extra.*.affiliation_id'] = ['nullable', 'integer'];
            $rules['org_extra.*.department'] = ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($hrDetail): void {
                $department = trim((string) $value);
                if ($department === '') {
                    return;
                }
                if (! in_array($department, User::registryDepartmentOptions(
                    $department,
                    (string) $this->input('jurisdiction', $hrDetail?->jurisdiction ?? ''),
                ), true)) {
                    $fail('部署を正しく選択してください。');
                }
            }];
            $rules['org_extra.*.section'] = ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($hrDetail): void {
                $section = trim((string) $value);
                if ($section === '') {
                    return;
                }
                $index = explode('.', $attribute)[1] ?? null;
                $department = trim((string) $this->input("org_extra.{$index}.department", ''));
                if (RegistrySectionByAssignment::isStandaloneSection($section) && $department !== '') {
                    $fail('庶務課は部署を選択しない場合のみ指定できます。');

                    return;
                }
                $allowed = RegistrySectionByAssignment::forSelect(
                    $department,
                    (string) $this->input('jurisdiction', $hrDetail?->jurisdiction),
                    $section,
                );
                if (! in_array($section, $allowed, true)) {
                    $fail('課を正しく選択してください。');
                }
            }];
            $rules['org_extra.*.team'] = ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($hrDetail): void {
                $team = trim((string) $value);
                if ($team === '') {
                    return;
                }
                $index = explode('.', $attribute)[1] ?? null;
                $allowed = RegistryTeamByAssignment::validValuesFor(
                    (string) $this->input("org_extra.{$index}.department", ''),
                    (string) $this->input('jurisdiction', $hrDetail?->jurisdiction),
                    (string) $this->input("org_extra.{$index}.section", ''),
                );
                if ($allowed !== [] && ! in_array($team, $allowed, true)) {
                    $fail('チームを正しく選択してください。');
                }
            }];
            $rules['org_extra.*.position'] = ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                $position = trim((string) $value);
                if ($position === '') {
                    return;
                }
                if (! in_array($position, User::registryPositionOptions($position), true)) {
                    $fail('役職を正しく選択してください。');
                }
            }];
        }

        if ($this->viewerCanEditIdentity()) {
            $target = $this->route('user') ?? auth()->user();
            $targetId = $target instanceof User ? $target->id : null;

            $rules['employee_id'] = EmployeeIdRules::rules(
                required: false,
                uniqueIgnoreUserId: $targetId,
                sometimes: true,
            );
            $rules['email'] = [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($targetId),
            ];
            $rules['last_name'] = ['sometimes', 'required', 'string', 'max:255'];
            $rules['first_name'] = ['sometimes', 'required', 'string', 'max:255'];
        }

        return $rules;
    }

    private function viewerCanEditIdentity(): bool
    {
        $target = $this->route('user') ?? auth()->user();
        $viewer = auth()->user();

        return $target instanceof User
            && $viewer !== null
            && $viewer->canEditEmployeeIdentity($target);
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if ($key !== null || ! is_array($validated) || ! array_key_exists('affiliation_code', $validated)) {
            return $validated;
        }

        $rawAffiliation = $validated['affiliation_code'];
        $validated['affiliation_code'] = $rawAffiliation === '' || $rawAffiliation === null
            ? null
            : (User::resolveAffiliationCodeForStorage((string) $rawAffiliation) ?? $rawAffiliation);

        return $validated;
    }

    /**
     * @return list<string>
     */
    public function editableFields(): array
    {
        $target = $this->route('user') ?? auth()->user();

        if (! $target instanceof User) {
            return [];
        }

        return EmployeeHrDetailAccess::editableFieldNames(auth()->user(), $target);
    }

    /**
     * @return array<string, mixed>
     */
    private function allFieldRules(): array
    {
        /** @var User|null $target */
        $target = $this->route('user');
        $hrDetail = $target?->hrDetail;
        $primaryOrg = $hrDetail
            ? \App\Support\RegistryOrgAssignment::splitForRegistryForm(
                $hrDetail->section_primary,
                $hrDetail->department_primary,
                $hrDetail->jurisdiction,
            )
            : ['section' => null, 'team' => null];
        $secondaryOrg = $hrDetail
            ? \App\Support\RegistryOrgAssignment::splitForRegistryForm(
                $hrDetail->section_secondary,
                $hrDetail->department_secondary,
                $hrDetail->jurisdiction,
            )
            : ['section' => null, 'team' => null];

        return [
            'name_kana_fullwidth' => ['nullable', 'string', 'max:255'],
            'name_kana_halfwidth' => ['nullable', 'string', 'max:255'],
            'affiliation_code' => ['nullable', 'string', Rule::in(array_keys(User::companyAffiliationSelectOptions($hrDetail?->affiliation_code)))],
            'employment_type' => ['nullable', 'string', Rule::in(User::employmentTypeOptions($hrDetail?->employment_type))],
            'has_social_insurance' => ['boolean'],
            'employment_status' => ['nullable', 'string', Rule::in(User::employmentStatusOptions($hrDetail?->employment_status))],
            'joined_at' => ['nullable', 'date'],
            'resigned_at' => ['nullable', 'date'],
            'last_working_day' => ['nullable', 'date'],
            'department_primary' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($hrDetail): void {
                    $department = trim((string) $value);

                    if ($department === '') {
                        return;
                    }

                    if (! in_array($department, User::registryDepartmentOptions(
                        $this->input('department_primary') ?: $hrDetail?->department_primary,
                        (string) $this->input('jurisdiction', $hrDetail?->jurisdiction ?? ''),
                    ), true)) {
                        $fail('部署を正しく選択してください。');
                    }
                },
            ],
            'section_primary' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($hrDetail, $primaryOrg): void {
                    $section = trim((string) $value);
                    $department = trim((string) $this->input('department_primary', $hrDetail?->department_primary ?? ''));

                    if ($section === '') {
                        return;
                    }

                    if (RegistrySectionByAssignment::isStandaloneSection($section) && $department !== '') {
                        $fail('庶務課は部署を選択しない場合のみ指定できます。');

                        return;
                    }

                    $allowed = RegistrySectionByAssignment::forSelect(
                        $department,
                        (string) $this->input('jurisdiction', $hrDetail?->jurisdiction),
                        (string) ($this->input('section_primary') ?: $primaryOrg['section']),
                    );

                    if (! in_array($section, $allowed, true)) {
                        $fail('課を正しく選択してください。');
                    }
                },
            ],
            'team_primary' => [
                'nullable',
                'string',
                Rule::in($this->allowedHrDetailTeamValues(
                    'primary',
                    (string) ($this->input('team_primary') ?: $primaryOrg['team']),
                )),
            ],
            'position_primary' => ['nullable', 'string', Rule::in(User::registryPositionOptions($hrDetail?->position_primary))],
            'department_secondary' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($hrDetail): void {
                    $department = trim((string) $value);

                    if ($department === '') {
                        return;
                    }

                    if (! in_array($department, User::registryDepartmentOptions(
                        $this->input('department_secondary') ?: $hrDetail?->department_secondary,
                        (string) $this->input('jurisdiction', $hrDetail?->jurisdiction ?? ''),
                    ), true)) {
                        $fail('部署を正しく選択してください。');
                    }
                },
            ],
            'section_secondary' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($hrDetail, $secondaryOrg): void {
                    $section = trim((string) $value);
                    $department = trim((string) $this->input('department_secondary', $hrDetail?->department_secondary ?? ''));

                    if ($section === '') {
                        return;
                    }

                    if (RegistrySectionByAssignment::isStandaloneSection($section) && $department !== '') {
                        $fail('庶務課は部署を選択しない場合のみ指定できます。');

                        return;
                    }

                    $allowed = RegistrySectionByAssignment::forSelect(
                        $department,
                        (string) $this->input('jurisdiction', $hrDetail?->jurisdiction),
                        (string) ($this->input('section_secondary') ?: $secondaryOrg['section']),
                    );

                    if (! in_array($section, $allowed, true)) {
                        $fail('課を正しく選択してください。');
                    }
                },
            ],
            'team_secondary' => [
                'nullable',
                'string',
                Rule::in($this->allowedHrDetailTeamValues(
                    'secondary',
                    (string) ($this->input('team_secondary') ?: $secondaryOrg['team']),
                )),
            ],
            'position_secondary' => ['nullable', 'string', Rule::in(User::registryPositionOptions($hrDetail?->position_secondary))],
            'jurisdiction' => ['nullable', 'string', Rule::in(User::OFFICE_LOCATIONS)],
            'gender' => ['nullable', 'string', Rule::in(EmployeeHrDetail::GENDERS)],
            'birth_date' => ['nullable', 'date'],
            'gmail_address' => ['nullable', 'email', 'max:255'],
            'nationality' => ['nullable', 'string', Rule::in(NationalityOptions::names())],
            'phone' => ['nullable', 'string', 'max:50'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'my_number_verified' => ['boolean'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'address_as_of_jan1' => ['nullable', 'string', 'max:2000'],
            'previous_withholding_slip' => ['nullable', 'string', 'max:255'],
            'resident_tax_switch_form' => ['nullable', 'string', 'max:255'],
            'money_forward_setup' => ['nullable', 'string', 'max:255'],
            'rakuraku_seisan_setup' => ['nullable', 'string', 'max:255'],
            'smarthr_setup' => ['nullable', 'string', 'max:255'],
            'business_card_onboarding' => ['nullable', 'string', 'max:255'],
            'health_check_received_at' => ['nullable', 'date'],
            'employment_insurance_number' => ['nullable', 'string', 'max:100'],
            'employment_insurance_applied_at' => ['nullable', 'date'],
            'health_pension_number' => ['nullable', 'string', 'max:100'],
            'health_pension_applied_at' => ['nullable', 'date'],
            'dependent_add_social_insurance' => ['nullable', 'string', 'max:255'],
            'has_pc' => ['boolean'],
            'pc_manufacturer' => ['nullable', 'string', 'max:100'],
            'pc_model' => ['nullable', 'string', 'max:100'],
            'mac_address' => ['nullable', 'string', 'max:100'],
            'has_mobile' => ['boolean'],
            'mobile_manufacturer' => ['nullable', 'string', 'max:100'],
            'setup_completed' => ['boolean'],
            'device_collected' => ['boolean'],
            'microsoft_account_removed' => ['boolean'],
            'gws_account_removed' => ['boolean'],
            'slack_account_removed' => ['boolean'],
            'company_phone' => ['nullable', 'string', 'max:255'],
            'resident_tax_transfer_form' => ['nullable', 'string', 'max:255'],
            'employment_insurance_withdrawal' => ['nullable', 'string', 'max:255'],
            'employment_insurance_withdrawal_applied_at' => ['nullable', 'date'],
            'health_pension_withdrawal' => ['nullable', 'string', 'max:255'],
            'health_pension_withdrawal_applied_at' => ['nullable', 'date'],
            'withholding_tax_slip' => ['nullable', 'string', 'max:255'],
            'separation_certificate' => ['nullable', 'string', 'max:255'],
            'resignation_certificate' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedHrDetailTeamValues(string $suffix, ?string $currentTeam = null): array
    {
        $departmentField = HrDetailOrgFormState::departmentField($suffix);
        $sectionField = HrDetailOrgFormState::sectionField($suffix);

        $allowed = \App\Support\RegistryTeamByAssignment::validValuesFor(
            (string) $this->input($departmentField, ''),
            (string) $this->input('jurisdiction', ''),
            (string) $this->input($sectionField, ''),
        );

        if ($currentTeam !== null && $currentTeam !== '' && ! in_array($currentTeam, $allowed, true)) {
            $allowed[] = $currentTeam;
        }

        return $allowed;
    }

    /**
     * @return list<string>
     */
    private function booleanFields(): array
    {
        return [
            'my_number_verified',
            'has_social_insurance',
            'has_pc',
            'has_mobile',
            'setup_completed',
            'device_collected',
            'microsoft_account_removed',
            'gws_account_removed',
            'slack_account_removed',
        ];
    }
}
