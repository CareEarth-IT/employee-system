<?php

namespace App\Http\Requests;

use App\Models\EquipmentPurchaseApplication;
use Illuminate\Foundation\Http\FormRequest;

class EquipmentPurchaseOrderUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('equipmentPurchase');

        return $application instanceof EquipmentPurchaseApplication
            && auth()->user()->canUpdateEquipmentPurchaseOrder($application);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_date' => ['nullable', 'date'],
            'arrival_date' => ['nullable', 'date'],
            'receipt_issued' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'order_date' => '注文日',
            'arrival_date' => '到着日',
            'receipt_issued' => '領収書発行',
        ];
    }
}
