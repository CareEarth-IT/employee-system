<?php

namespace App\Http\Requests;

use App\Models\EquipmentPurchaseApplication;
use App\Services\EquipmentPurchaseSubmissionPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EquipmentPurchaseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->canAccessEquipmentPurchaseSettlement();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $destination = $this->input('item_destination');
        $delivery = $this->input('delivery_destination');
        $isPurchased = EquipmentPurchaseApplication::isPurchasedApplicationType(
            $this->input('application_type')
        );

        return [
            'application_type' => ['required', 'string', Rule::in(array_keys(EquipmentPurchaseApplication::TYPE_LABELS))],
            'purchase_site' => $isPurchased
                ? ['required', 'string', 'max:50']
                : ['required', 'string', Rule::in(EquipmentPurchaseApplication::PURCHASE_SITES)],
            'purchase_site_url' => $isPurchased
                ? ['nullable', 'url', 'max:2000']
                : ['required', 'url', 'max:2000'],
            'product_name' => ['required', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:255'],
            'color_model' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'price_including_tax' => [
                'required',
                'integer',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $type = $this->input('application_type');

                    if (! is_string($type)) {
                        return;
                    }

                    if (! EquipmentPurchaseApplication::priceMatchesApplicationType($type, (int) $value)) {
                        $message = EquipmentPurchaseApplication::priceValidationMessageForType($type);

                        if ($message) {
                            $fail($message);
                        }
                    }
                },
            ],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'purchase_reason' => ['required', 'string', 'max:5000'],
            'item_destination' => ['required', 'string', Rule::in(array_keys(EquipmentPurchaseApplication::ITEM_DESTINATIONS))],
            'department' => [
                'nullable',
                Rule::requiredIf(function () use ($destination) {
                    if ($destination === EquipmentPurchaseApplication::DESTINATION_DEPARTMENT_ALL) {
                        return true;
                    }

                    if ($destination === EquipmentPurchaseApplication::DESTINATION_SECTION_ONLY) {
                        return blank($this->input('section'));
                    }

                    return false;
                }),
                'string',
                'max:255',
            ],
            'section' => [
                'nullable',
                Rule::requiredIf(function () use ($destination) {
                    return $destination === EquipmentPurchaseApplication::DESTINATION_SECTION_ONLY
                        && blank($this->input('department'));
                }),
                'string',
                'max:255',
            ],
            'onsite_name' => [
                'nullable',
                Rule::requiredIf($destination === EquipmentPurchaseApplication::DESTINATION_ONSITE),
                'string',
                'max:255',
            ],
            'delivery_destination' => $isPurchased
                ? ['nullable', 'string', Rule::in(array_keys(EquipmentPurchaseApplication::DELIVERY_DESTINATIONS))]
                : ['required', 'string', Rule::in(array_keys(EquipmentPurchaseApplication::DELIVERY_DESTINATIONS))],
            'delivery_zip' => [
                'nullable',
                Rule::requiredIf(! $isPurchased && $delivery === EquipmentPurchaseApplication::DELIVERY_OTHER),
                'string',
                'max:10',
            ],
            'delivery_address' => [
                'nullable',
                Rule::requiredIf(! $isPurchased && $delivery === EquipmentPurchaseApplication::DELIVERY_OTHER),
                'string',
                'max:500',
            ],
            'delivery_recipient_name' => [
                'nullable',
                Rule::requiredIf(! $isPurchased && $delivery === EquipmentPurchaseApplication::DELIVERY_OTHER),
                'string',
                'max:255',
            ],
            'delivery_recipient_phone' => [
                'nullable',
                Rule::requiredIf(! $isPurchased && $delivery === EquipmentPurchaseApplication::DELIVERY_OTHER),
                'string',
                'max:30',
            ],
            'purchase_urgency' => $isPurchased
                ? ['nullable', 'string', Rule::in(array_keys(EquipmentPurchaseApplication::PURCHASE_URGENCIES))]
                : ['required', 'string', Rule::in(array_keys(EquipmentPurchaseApplication::PURCHASE_URGENCIES))],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! EquipmentPurchaseSubmissionPeriod::canSubmitToday()) {
                $validator->errors()->add(
                    'application_type',
                    EquipmentPurchaseSubmissionPeriod::closedMessage(),
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $isPurchased = EquipmentPurchaseApplication::isPurchasedApplicationType(
            $this->input('application_type')
        );

        return [
            'application_type.required' => '申請タイプを選択してください。',
            'purchase_site.required' => $isPurchased
                ? '購入店舗名を入力してください。'
                : '購入サイトを選択してください。',
            'purchase_site_url.required' => '購入サイト URLを入力してください。',
            'purchase_site_url.url' => '購入サイト URLは正しいURL形式で入力してください。',
            'product_name.required' => '購入商品名を入力してください。',
            'quantity.required' => '数量を入力してください。',
            'quantity.min' => '数量は1以上で入力してください。',
            'price_including_tax.required' => '価格（税込）を入力してください。',
            'price_including_tax.min' => '価格（税込）は1以上で入力してください。',
            'purchase_reason.required' => '購入理由を入力してください。',
            'item_destination.required' => '備品の利用先を選択してください。',
            'department.required' => '備品の利用先で「部全体用」を選んだ場合、または「部・課専用」で課が未入力の場合は部を入力してください。',
            'section.required' => '備品の利用先で「部・課専用」を選び、部が未入力の場合は課を入力してください。',
            'onsite_name.required' => '備品の利用先で「現場」を選んだ場合は現場名を入力してください。',
            'delivery_destination.required' => '備品の届先を選択してください。',
            'delivery_zip.required' => '届先で「その他」を選んだ場合はお届け先〒を入力してください。',
            'delivery_address.required' => '届先で「その他」を選んだ場合はお届け先を入力してください。',
            'delivery_recipient_name.required' => '届先で「その他」を選んだ場合は受取人氏名を入力してください。',
            'delivery_recipient_phone.required' => '届先で「その他」を選んだ場合は受取人電話を入力してください。',
            'purchase_urgency.required' => '購入希望日を選択してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $isPurchased = EquipmentPurchaseApplication::isPurchasedApplicationType(
            $this->input('application_type')
        );

        return [
            'application_type' => '申請タイプ',
            'purchase_site' => $isPurchased ? '購入店舗名' : '購入サイト',
            'purchase_site_url' => '購入サイト URL',
            'product_name' => '購入商品名',
            'quantity' => '数量',
            'price_including_tax' => '価格（税込）',
            'purchase_reason' => '購入理由',
            'item_destination' => '備品の利用先',
            'department' => '部',
            'section' => '課',
            'onsite_name' => '現場名',
            'delivery_destination' => '備品の届先',
            'delivery_zip' => 'お届け先〒',
            'delivery_address' => 'お届け先',
            'delivery_recipient_name' => '受取人氏名',
            'delivery_recipient_phone' => '受取人電話',
            'purchase_urgency' => '購入希望日',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        return $this->normalizeConditionalFields($validated);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeConditionalFields(array $validated): array
    {
        $itemDestination = $validated['item_destination'] ?? '';

        if (! in_array($itemDestination, [
            EquipmentPurchaseApplication::DESTINATION_DEPARTMENT_ALL,
            EquipmentPurchaseApplication::DESTINATION_SECTION_ONLY,
        ], true)) {
            $validated['department'] = null;
        }

        if ($itemDestination !== EquipmentPurchaseApplication::DESTINATION_SECTION_ONLY) {
            $validated['section'] = null;
        } elseif (blank($validated['section'] ?? null)) {
            $validated['section'] = null;
        }

        if (blank($validated['department'] ?? null)) {
            $validated['department'] = null;
        }

        if ($itemDestination !== EquipmentPurchaseApplication::DESTINATION_ONSITE
            || blank($validated['onsite_name'] ?? null)) {
            $validated['onsite_name'] = null;
        }

        if (blank($validated['purchase_site_url'] ?? null)) {
            $validated['purchase_site_url'] = null;
        }

        if (blank($validated['delivery_destination'] ?? null)) {
            $validated['delivery_destination'] = null;
            $validated['delivery_zip'] = null;
            $validated['delivery_address'] = null;
            $validated['delivery_recipient_name'] = null;
            $validated['delivery_recipient_phone'] = null;
        } elseif (($validated['delivery_destination'] ?? '') !== EquipmentPurchaseApplication::DELIVERY_OTHER) {
            $validated['delivery_zip'] = null;
            $validated['delivery_address'] = null;
            $validated['delivery_recipient_name'] = null;
            $validated['delivery_recipient_phone'] = null;
        }

        if (blank($validated['purchase_urgency'] ?? null)) {
            $validated['purchase_urgency'] = null;
        }

        return $validated;
    }
}
