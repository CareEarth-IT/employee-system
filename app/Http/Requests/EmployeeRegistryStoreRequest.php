<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEmployeeRegistryFields;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeRegistryStoreRequest extends FormRequest
{
    use ValidatesEmployeeRegistryFields;

    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageEmployeeRegistry();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->registryFieldRules(),
            'password' => $this->registryPasswordRules(required: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->registryFieldMessages(),
            'password.required' => 'パスワードを入力してください。',
            'password.confirmed' => 'パスワード（確認）が一致しません。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->registryFieldAttributes();
    }
}
