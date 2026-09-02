<?php

namespace App\Http\Requests;

use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\EmployeeHrDetailAccess;
use App\Support\CompanyPhone;
use App\Support\HrDetailOrgFormState;
use App\Support\NationalityOptions;
use App\Support\RegistrySectionByAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeHrDetailUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user') ?? auth()->user();

        return $target instanceof User
            && EmployeeHrDetailAccess::canUpdateAny(auth()->user(), $target);
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

        foreach (['section_primary', 'section_secondary', 'team_primary', 'team_secondary'] as $field) {
            if ($this->has($field) && trim((string) $this->input($field)) === '') {
                $this->merge([$field => null]);
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

        return $rules;
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
            'affiliation_code' => ['nullable', 'string', Rule::in(User::affiliationCodeOptions($hrDetail?->affiliation_code))],
            'employment_type' => ['nullable', 'string', Rule::in(User::employmentTypeOptions($hrDetail?->employment_type))],
            'employment_status' => ['nullable', 'string', Rule::in(User::employmentStatusOptions($hrDetail?->employment_status))],
            'resigned_at' => ['nullable', 'date'],
            'last_working_day' => ['nullable', 'date'],
            'department_primary' => ['nullable', 'string', Rule::in(User::registryDepartmentOptions($this->input('department_primary') ?: $hrDetail?->department_primary))],
            'section_primary' => [
                'nullable',
                'string',
                Rule::in(RegistrySectionByAssignment::forSelect(
                    (string) $this->input('department_primary', $hrDetail?->department_primary),
                    (string) $this->input('jurisdiction', $hrDetail?->jurisdiction),
                    (string) ($this->input('section_primary') ?: $primaryOrg['section']),
                )),
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
            'department_secondary' => ['nullable', 'string', Rule::in(User::registryDepartmentOptions($this->input('department_secondary') ?: $hrDetail?->department_secondary))],
            'section_secondary' => [
                'nullable',
                'string',
                Rule::in(RegistrySectionByAssignment::forSelect(
                    (string) $this->input('department_secondary', $hrDetail?->department_secondary),
                    (string) $this->input('jurisdiction', $hrDetail?->jurisdiction),
                    (string) ($this->input('section_secondary') ?: $secondaryOrg['section']),
                )),
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
