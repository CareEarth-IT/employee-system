<?php

namespace App\Http\Requests\Concerns;

use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\EmployeeIdRules;
use App\Support\NationalityOptions;
use App\Support\RegistryGrAssignment;
use App\Support\RegistryOrgFormState;
use App\Support\RegistrySectionByAssignment;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait ValidatesEmployeeRegistryFields
{
    protected function mergeRegistryInputForValidation(): void
    {
        $nullable = static fn (mixed $value): ?string => (($text = trim((string) $value)) !== '' ? $text : null);

        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'email' => strtolower(trim((string) $this->input('email', ''))),
            'department' => trim((string) $this->input('department', '')),
            'company' => trim((string) $this->input('company', '')),
            'section' => $nullable($this->input('section', '')),
            'team' => $nullable($this->input('team', '')),
            'employee_id' => preg_replace('/\D/', '', (string) $this->input('employee_id', '')) ?? '',
            'name_kana' => $nullable($this->input('name_kana', '')),
            'english_name' => $nullable($this->input('english_name', '')),
            'birth_date' => $nullable($this->input('birth_date', '')),
            'nationality' => NationalityOptions::toDisplayName($nullable($this->input('nationality', ''))),
            'gender' => $nullable($this->input('gender', '')),
            'remarks' => $nullable($this->input('remarks', '')),
            'joined_at' => $nullable($this->input('joined_at', '')),
            'employment_status' => trim((string) $this->input('employment_status', '在籍')),
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->mergeRegistryInputForValidation();
    }

    /**
     * @return array<string, mixed>
     */
    protected function registryFieldRules(
        ?int $uniqueIgnoreUserId = null,
        ?string $currentDepartment = null,
        ?string $currentCompany = null,
        ?string $currentSection = null,
        ?string $currentTeam = null,
        ?string $currentEmploymentStatus = null,
        bool $splitSectionTeam = true,
        bool $requireSectionForGrDepartment = false,
    ): array {
        $employeeIdRules = EmployeeIdRules::rules(
            required: true,
            uniqueIgnoreUserId: $uniqueIgnoreUserId,
        );

        return [
            'name' => ['required', 'string', 'max:255', 'regex:/[\s　]/u'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($uniqueIgnoreUserId),
            ],
            'employee_id' => $employeeIdRules,
            'department' => ['required', 'string', Rule::in(User::registryDepartmentOptions($currentDepartment))],
            'company' => ['required', 'string', Rule::in(User::companyOptions($currentCompany))],
            'section' => [
                Rule::requiredIf(fn (): bool => $requireSectionForGrDepartment
                    && (string) $this->input('department', '') === RegistryGrAssignment::DEPARTMENT),
                'nullable',
                'string',
                Rule::in($splitSectionTeam
                    ? RegistrySectionByAssignment::forSelect(
                        (string) $this->input('department', ''),
                        (string) $this->input('location', ''),
                        $currentSection,
                    )
                    : RegistryOrgFormState::combinedSectionOptions(
                        (string) $this->input('department', ''),
                        (string) $this->input('location', ''),
                        $currentSection,
                    )),
            ],
            'team' => $splitSectionTeam ? [
                'nullable',
                'string',
                Rule::in($this->allowedRegistryTeamValues($currentTeam)),
            ] : ['nullable'],
            'location' => ['required', 'string', Rule::in(User::OFFICE_LOCATIONS)],
            'employment_type' => ['required', 'string', Rule::in(User::EMPLOYMENT_TYPE_OPTIONS)],
            'employment_status' => ['required', 'string', Rule::in(User::employmentStatusOptions($currentEmploymentStatus))],
            'name_kana' => ['nullable', 'string', 'max:255'],
            'english_name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'joined_at' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', Rule::in(NationalityOptions::names())],
            'gender' => ['nullable', 'string', Rule::in(EmployeeHrDetail::GENDERS)],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function registryFieldMessages(): array
    {
        return [
            'name.required' => '名前を入力してください。',
            'name.regex' => '名前は姓と名の間にスペースを入れてください（全角・半角どちらでも可）。',
            'email.required' => 'アドレスを入力してください。',
            'email.email' => 'アドレスの形式が正しくありません。',
            'email.unique' => 'このアドレスは既に使用されています。',
            'employee_id.required' => 'IDを入力してください。',
            'employee_id.digits' => EmployeeIdRules::FORMAT_MESSAGE,
            'employee_id.unique' => 'この社員IDは既に使用されています。',
            'department.required' => '部署を選択してください。',
            'department.in' => '部署を正しく選択してください。',
            'company.required' => '所属会社を選択してください。',
            'company.in' => '所属会社を正しく選択してください。',
            'section.required' => '課を選択してください。',
            'section.in' => '課を正しく選択してください。',
            'team.in' => 'チームを正しく選択してください。',
            'location.required' => '管轄を選択してください。',
            'location.in' => '管轄を正しく選択してください。',
            'employment_type.required' => '雇用形態を選択してください。',
            'employment_type.in' => '雇用形態を正しく選択してください。',
            'employment_status.required' => '状況を選択してください。',
            'employment_status.in' => '状況を正しく選択してください。',
            'birth_date.date' => '生年月日は正しい日付形式で入力してください。',
            'joined_at.date' => '入社予定日(目安)は正しい日付形式で入力してください。',
            'gender.in' => '性別を正しく選択してください。',
            'nationality.in' => '国籍を正しく選択してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function registryFieldAttributes(): array
    {
        return [
            'name' => '名前',
            'email' => 'アドレス',
            'password' => 'パスワード',
            'employee_id' => 'ID',
            'department' => '部署',
            'company' => '所属会社',
            'section' => '課',
            'team' => 'チーム',
            'location' => '管轄',
            'employment_type' => '雇用形態',
            'employment_status' => '状況',
            'name_kana' => 'ナマエ',
            'english_name' => 'Name',
            'birth_date' => '生年月日',
            'joined_at' => '入社予定日(目安)',
            'nationality' => '国籍',
            'gender' => '性別',
            'remarks' => '備考',
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowedRegistryTeamValues(?string $currentTeam = null): array
    {
        $allowed = \App\Support\RegistryTeamByAssignment::validValuesFor(
            (string) $this->input('department', ''),
            (string) $this->input('location', ''),
            (string) $this->input('section', ''),
        );

        if ($currentTeam !== null && $currentTeam !== '' && ! in_array($currentTeam, $allowed, true)) {
            $allowed[] = $currentTeam;
        }

        return $allowed;
    }

    /**
     * @return array<string, mixed>
     */
    protected function registryPasswordRules(bool $required): array
    {
        $rules = ['string', 'confirmed', Password::min(8)];

        array_unshift($rules, $required ? 'required' : 'nullable');

        return $rules;
    }
}
