<?php

namespace App\Http\Requests;

use App\Models\EquipmentPurchaseApplication;
use Illuminate\Foundation\Http\FormRequest;

class EquipmentPurchaseConsumableUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('equipmentPurchase');

        return $application instanceof EquipmentPurchaseApplication
            && auth()->user()->canUpdateEquipmentPurchaseConsumable($application);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_consumable' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'is_consumable' => '消耗品',
        ];
    }
}
