<?php

namespace App\Http\Requests;

use App\Models\EquipmentPurchaseApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EquipmentPurchaseApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('equipmentPurchase');

        return $application instanceof EquipmentPurchaseApplication
            && auth()->user()->canApproveEquipmentPurchase($application);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $confirmerRules = auth()->user()?->isGlobalEquipmentApprover()
            ? ['required', 'string', 'max:100']
            : ['nullable', 'string', 'max:100'];

        return [
            'approved_at' => ['required', 'date'],
            'approval_decision' => ['required', 'string', Rule::in(array_keys(EquipmentPurchaseApplication::APPROVAL_DECISIONS))],
            'approver_display_name' => $confirmerRules,
            'approval_memo' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'approved_at.required' => '承認日時を入力してください。',
            'approval_decision.required' => '承認（許可 / 許可しない）を選択してください。',
            'approval_decision.in' => '承認の選択が正しくありません。',
            'approver_display_name.required' => '確認者名を入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'approved_at' => '承認日時',
            'approval_decision' => '承認',
            'approver_display_name' => '確認者',
            'approval_memo' => 'メモ',
        ];
    }
}
