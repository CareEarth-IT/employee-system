<?php

namespace App\Http\Requests;

use App\Models\AffiliationHistory;
use App\Models\User;
use App\Support\EmployeeIdRules;
use Illuminate\Foundation\Http\FormRequest;

class AffiliationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $isCurrent = $this->boolean('is_current');

        $this->merge([
            'enrollment_status' => $isCurrent
                ? AffiliationHistory::STATUS_ENROLLED
                : AffiliationHistory::STATUS_MOVED,
        ]);

        if ($isCurrent) {
            $this->merge(['end_date' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $enrolled = AffiliationHistory::STATUS_ENROLLED;
        $target = $this->route('user');
        if (! $target instanceof User) {
            $affiliation = $this->route('affiliation');
            $target = $affiliation instanceof AffiliationHistory ? $affiliation->user : auth()->user();
        }
        $targetId = $target instanceof User ? $target->id : null;

        return [
            'employee_id' => EmployeeIdRules::rules(required: false, uniqueIgnoreUserId: $targetId),
            'enrollment_status' => ['required', 'string', 'in:'.implode(',', AffiliationHistory::ENROLLMENT_STATUSES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', "required_unless:enrollment_status,{$enrolled}", 'date', 'after_or_equal:start_date'],
            'company' => ['nullable', 'string', 'in:'.implode(',', User::COMPANY_NAMES)],
            'location' => ['nullable', 'string', 'in:'.implode(',', User::OFFICE_LOCATIONS)],
            'department' => ['nullable', 'string', 'max:255'],
            'section' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'job_description' => ['nullable', 'string', 'max:5000'],
            'action' => ['nullable', 'string', 'in:save,continue'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.required_unless' => '在籍中以外の場合は終了日を入力してください。',
            'enrollment_status.required' => '在籍状況を選択してください。',
            'start_date.required' => '開始日を入力してください。',
            'start_date.date' => '開始日は正しい日付形式で入力してください。',
            'end_date.date' => '終了日は正しい日付形式で入力してください。',
            'end_date.after_or_equal' => '終了日は開始日以降の日付を指定してください。',
            'company.in' => '会社名の選択が正しくありません。',
            'location.in' => '拠点の選択が正しくありません。',
            'employee_id.digits' => EmployeeIdRules::FORMAT_MESSAGE,
            'employee_id.unique' => 'この社員IDは既に使用されています。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employee_id' => '社員ID',
            'enrollment_status' => '在籍状況',
            'start_date' => '開始日',
            'end_date' => '終了日',
            'company' => '会社名',
            'location' => '拠点',
            'department' => '部',
            'section' => '課',
            'position' => '役職',
            'job_description' => '業務内容',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        unset($validated['employee_id'], $validated['action']);

        return $validated;
    }
}
