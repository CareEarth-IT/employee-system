<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use App\Support\EmployeeIdRules;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait ValidatesEmployeeRegistryFields
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'email' => strtolower(trim((string) $this->input('email', ''))),
            'department' => trim((string) $this->input('department', '')),
            'employee_id' => preg_replace('/\D/', '', (string) $this->input('employee_id', '')) ?? '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function registryFieldRules(?int $uniqueIgnoreUserId = null): array
    {
        $employeeIdRules = EmployeeIdRules::rules(
            required: true,
            uniqueIgnoreUserId: $uniqueIgnoreUserId,
        );

        if ($uniqueIgnoreUserId === null) {
            $employeeIdRules[] = Rule::unique('users', 'employee_id');
        }

        return [
            'name' => ['required', 'string', 'max:255', 'regex:/[\s　]/u'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($uniqueIgnoreUserId),
            ],
            'employee_id' => $employeeIdRules,
            'department' => ['required', 'string', 'max:255', 'regex:/\S/u'],
            'location' => ['required', 'string', Rule::in(User::OFFICE_LOCATIONS)],
            'employment_type' => ['required', 'string', Rule::in(User::EMPLOYMENT_TYPE_OPTIONS)],
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
            'department.required' => '所属部署を入力してください。',
            'department.regex' => '所属部署を入力してください。',
            'location.required' => '拠点を選択してください。',
            'location.in' => '拠点を正しく選択してください。',
            'employment_type.required' => '雇用形態を選択してください。',
            'employment_type.in' => '雇用形態を正しく選択してください。',
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
            'location' => '拠点',
            'employment_type' => '雇用形態',
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
