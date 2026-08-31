<?php

namespace App\Http\Requests\Concerns;

use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\EmployeeIdRules;
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
            'section' => $nullable($this->input('section', '')),
            'employee_id' => preg_replace('/\D/', '', (string) $this->input('employee_id', '')) ?? '',
            'name_kana' => $nullable($this->input('name_kana', '')),
            'english_name' => $nullable($this->input('english_name', '')),
            'abbreviated_name' => $nullable($this->input('abbreviated_name', '')),
            'nationality' => $nullable($this->input('nationality', '')),
            'gender' => $nullable($this->input('gender', '')),
            'remarks' => $nullable($this->input('remarks', '')),
            'joined_at' => $nullable($this->input('joined_at', '')),
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
        ?string $currentSection = null,
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
            'section' => ['nullable', 'string', Rule::in(User::registrySectionOptions($currentSection))],
            'location' => ['required', 'string', Rule::in(User::OFFICE_LOCATIONS)],
            'employment_type' => ['required', 'string', Rule::in(User::EMPLOYMENT_TYPE_OPTIONS)],
            'name_kana' => ['nullable', 'string', 'max:255'],
            'english_name' => ['nullable', 'string', 'max:255'],
            'abbreviated_name' => ['nullable', 'string', 'max:10'],
            'joined_at' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:255'],
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
            'name.required' => '氏名を入力してください。',
            'name.regex' => '氏名は姓と名の間にスペースを入れてください（全角・半角どちらでも可）。',
            'email.required' => 'アドレスを入力してください。',
            'email.email' => 'アドレスの形式が正しくありません。',
            'email.unique' => 'このアドレスは既に使用されています。',
            'employee_id.required' => '社員IDを入力してください。',
            'employee_id.digits' => EmployeeIdRules::FORMAT_MESSAGE,
            'employee_id.unique' => 'この社員IDは既に使用されています。',
            'department.required' => '所属部署を選択してください。',
            'department.in' => '所属部署を正しく選択してください。',
            'section.in' => '課を正しく選択してください。',
            'location.required' => '拠点を選択してください。',
            'location.in' => '拠点を正しく選択してください。',
            'employment_type.required' => '雇用形態を選択してください。',
            'employment_type.in' => '雇用形態を正しく選択してください。',
            'abbreviated_name.max' => '短縮表示は10文字以内で入力してください。',
            'joined_at.date' => '入社予定日は正しい日付形式で入力してください。',
            'gender.in' => '性別を正しく選択してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function registryFieldAttributes(): array
    {
        return [
            'name' => '氏名',
            'email' => 'アドレス',
            'password' => 'パスワード',
            'employee_id' => '社員ID',
            'department' => '所属部署',
            'section' => '課',
            'location' => '拠点',
            'employment_type' => '雇用形態',
            'name_kana' => 'ナマエ',
            'english_name' => 'Name',
            'abbreviated_name' => '短縮表示',
            'joined_at' => '入社予定日',
            'nationality' => '国籍',
            'gender' => '性別',
            'remarks' => '備考',
        ];
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
