<div class="bg-white border border-slate-300 rounded-lg p-6 mb-6"
    @if ($canEditIdentity ?? false)
        data-profile-inline-edit
        data-update-url="{{ $identityUpdateUrl }}"
        data-csrf="{{ csrf_token() }}"
    @endif
>
    <h2 class="font-bold mb-4">Google Workspace 連携情報</h2>
    @if ($canEditIdentity ?? false)
        <p class="mb-4 text-xs text-slate-500">情報システム部のみ編集可能です。社員ID・メールアドレスをダブルクリックで編集できます。</p>
    @endif

    <div class="grid sm:grid-cols-2 gap-4">
        @include('profile.partials.inline-field', [
            'field' => 'employee_id',
            'label' => '社員ID'.(($canEditIdentity ?? false) ? '' : ' （自動取得）'),
            'value' => $user->employee_id ?? '',
            'editable' => $canEditIdentity ?? false,
        ])
        @include('profile.partials.inline-field', [
            'field' => 'email',
            'label' => 'メールアドレス'.(($canEditIdentity ?? false) ? '' : ' （自動取得）'),
            'type' => 'email',
            'value' => $user->email ?? '',
            'editable' => $canEditIdentity ?? false,
        ])
        <div>
            <label class="block text-sm text-slate-500 mb-1">姓 <span class="text-xs">(自動取得)</span></label>
            <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2">{{ $user->last_name ?? '—' }}</p>
        </div>
        <div>
            <label class="block text-sm text-slate-500 mb-1">名 <span class="text-xs">(自動取得)</span></label>
            <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2">{{ $user->first_name ?? '—' }}</p>
        </div>
    </div>
</div>
