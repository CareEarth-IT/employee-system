<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\EmployeeHrDetailAccess;
use App\Support\CompanyPhone;
use Illuminate\Foundation\Http\FormRequest;

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
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allRules = $this->allFieldRules();
        $editable = array_flip($this->editableFields());

        return array_intersect_key($allRules, $editable);
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
        return [
            'name_kana_fullwidth' => ['nullable', 'string', 'max:255'],
            'name_kana_halfwidth' => ['nullable', 'string', 'max:255'],
            'affiliation_code' => ['nullable', 'string', 'max:50'],
            'employment_type' => ['nullable', 'string', 'max:100'],
            'employment_status' => ['nullable', 'string', 'max:100'],
            'resigned_at' => ['nullable', 'date'],
            'last_working_day' => ['nullable', 'date'],
            'residence_status' => ['nullable', 'string', 'max:255'],
            'residence_expires_at' => ['nullable', 'date'],
            'residence_renewal_memo' => ['nullable', 'string', 'max:5000'],
            'residence_card_renewal_status' => ['nullable', 'string', 'max:255'],
            'department_primary' => ['nullable', 'string', 'max:255'],
            'section_primary' => ['nullable', 'string', 'max:255'],
            'position_primary' => ['nullable', 'string', 'max:255'],
            'department_secondary' => ['nullable', 'string', 'max:255'],
            'section_secondary' => ['nullable', 'string', 'max:255'],
            'position_secondary' => ['nullable', 'string', 'max:255'],
            'jurisdiction' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date'],
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
