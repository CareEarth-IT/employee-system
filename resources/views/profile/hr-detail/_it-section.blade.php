@php
    use App\Models\EmployeeHrDetail;

    $d = $hrDetail;
    $roIt = ! ($access['it']['edit'] ?? false);
    $roItDevice = ! (($access['it']['edit'] ?? false) || ($access['it']['edit_self_device'] ?? false));
@endphp

<section class="space-y-4">
    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-200 pb-2">
        <h2 class="text-lg font-bold">IT・デバイス</h2>
        @if ($roIt && ($access['it']['edit_self_device'] ?? false))
            <span class="text-xs text-slate-500">メーカー・型番・MACは本人編集可（その他は閲覧のみ）</span>
        @elseif ($roIt)
            <span class="text-xs text-slate-500">閲覧のみ（編集は情シス）</span>
        @endif
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @include('partials.form-field', [
            'name' => 'company_phone',
            'label' => '社用の電話番号',
            'type' => 'textarea',
            'rows' => 2,
            'hint' => '複数ある場合はカンマ区切り',
            'value' => $d->company_phone,
            'readonly' => $roIt,
        ])
        @include('partials.form-field', ['name' => 'has_pc', 'label' => 'PC', 'type' => 'checkbox', 'value' => $d->has_pc, 'readonly' => $roIt])
        @include('partials.form-field', [
            'name' => 'pc_manufacturer',
            'label' => 'PCメーカー',
            'type' => 'select',
            'options' => EmployeeHrDetail::PC_MANUFACTURERS,
            'value' => $d->pc_manufacturer,
            'readonly' => $roItDevice,
        ])
        @include('partials.form-field', ['name' => 'pc_model', 'label' => 'PCの型番', 'value' => $d->pc_model, 'readonly' => $roItDevice])
        @include('partials.form-field', ['name' => 'mac_address', 'label' => 'PCのMACアドレス', 'value' => $d->mac_address, 'readonly' => $roItDevice])
        @include('partials.form-field', ['name' => 'has_mobile', 'label' => 'モバイル', 'type' => 'checkbox', 'value' => $d->has_mobile, 'readonly' => $roIt])
        @include('partials.form-field', ['name' => 'mobile_manufacturer', 'label' => 'モバイル機種名', 'value' => $d->mobile_manufacturer, 'readonly' => $roItDevice])
        @include('partials.form-field', ['name' => 'setup_completed', 'label' => 'セットアップ', 'type' => 'checkbox', 'value' => $d->setup_completed, 'readonly' => $roIt])
        @include('partials.form-field', ['name' => 'device_collected', 'label' => '退職者デバイス回収', 'type' => 'checkbox', 'value' => $d->device_collected, 'readonly' => $roIt])
        @include('partials.form-field', ['name' => 'microsoft_account_removed', 'label' => 'Microsoft（退職者削除用）', 'type' => 'checkbox', 'value' => $d->microsoft_account_removed, 'readonly' => $roIt])
        @include('partials.form-field', ['name' => 'gws_account_removed', 'label' => 'GWS（退職者削除用）', 'type' => 'checkbox', 'value' => $d->gws_account_removed, 'readonly' => $roIt])
        @include('partials.form-field', ['name' => 'slack_account_removed', 'label' => 'slack（退職者削除用）', 'type' => 'checkbox', 'value' => $d->slack_account_removed, 'readonly' => $roIt])
    </div>
</section>
