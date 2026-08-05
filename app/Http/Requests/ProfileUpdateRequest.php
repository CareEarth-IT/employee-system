<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\EmployeeIdRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user') ?? auth()->user();

        if (! $target instanceof User) {
            return false;
        }

        $viewer = auth()->user();
        if ($viewer === null) {
            return false;
        }

        $touchesIdentity = $this->exists('email') || $this->exists('employee_id');
        $touchesProfile = collect([
            'english_name',
            'name_kana',
            'abbreviated_name',
            'joined_at',
            'nationality',
            'languages',
            'self_introduction',
            'photo',
        ])->contains(fn (string $key): bool => $this->exists($key));

        if ($touchesIdentity && ! $viewer->canEditEmployeeIdentity($target)) {
            return false;
        }

        if ($touchesProfile && ! $viewer->canEditProfile($target)) {
            return false;
        }

        if (! $touchesIdentity && ! $touchesProfile) {
            return $viewer->canEditProfile($target);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->route('user') ?? auth()->user();
        $targetId = $target instanceof User ? $target->id : null;
        $viewer = auth()->user();
        $canEditIdentity = $viewer?->canEditEmployeeIdentity($target instanceof User ? $target : null) ?? false;

        $rules = [
            'english_name' => ['nullable', 'string', 'max:255'],
            'name_kana' => ['nullable', 'string', 'max:255'],
            'abbreviated_name' => ['nullable', 'string', 'max:10'],
            'joined_at' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'languages' => ['nullable', 'string', 'max:2000'],
            'self_introduction' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ];

        if ($canEditIdentity) {
            $rules['employee_id'] = EmployeeIdRules::rules(
                required: true,
                uniqueIgnoreUserId: $targetId,
                sometimes: true,
            );
            $rules['email'] = [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($targetId),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.image' => '写真は画像ファイルを選択してください。',
            'photo.max' => '写真のファイルサイズは2MB以下にしてください。',
            'abbreviated_name.max' => '略氏名は10文字以内で入力してください。',
            'employee_id.required' => '社員IDを入力してください。',
            'employee_id.digits' => EmployeeIdRules::FORMAT_MESSAGE,
            'employee_id.unique' => 'この社員IDは既に使用されています。',
            'email.required' => 'メールアドレスを入力してください。',
            'email.email' => 'メールアドレスの形式が正しくありません。',
            'email.unique' => 'このメールアドレスは既に使用されています。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employee_id' => '社員ID',
            'email' => 'メールアドレス',
        ];
    }
}
