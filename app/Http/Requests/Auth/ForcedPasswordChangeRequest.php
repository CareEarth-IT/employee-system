<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class ForcedPasswordChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->must_change_password;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'password' => '新しいパスワード',
            'password_confirmation' => '新しいパスワード（確認）',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->string('password')->toString() === 'password') {
                $validator->errors()->add('password', '初期パスワードと同じ文字列は使えません。別のパスワードを設定してください。');
            }
        });
    }
}
