<div class="bg-white border border-slate-300 rounded-lg p-6 mb-6"
    @if ($canEditIdentity ?? false)
        data-profile-inline-edit
        data-update-url="{{ $identityUpdateUrl }}"
        data-csrf="{{ csrf_token() }}"
    @endif
>
    <h2 class="mb-4 text-lg font-bold">Google Workspace 連携情報</h2>
    @if ($canEditIdentity ?? false)
        <p class="mb-4 text-sm text-slate-500">情報システム部のみ編集可能です。社員ID・メールアドレスをダブルクリックで編集できます。</p>
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
            <label class="mb-1 block text-base text-slate-500">姓 <span class="text-sm">(自動取得)</span></label>
            <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-base">{{ $user->last_name ?? '—' }}</p>
        </div>
        <div>
            <label class="mb-1 block text-base text-slate-500">名 <span class="text-sm">(自動取得)</span></label>
            <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-base">{{ $user->first_name ?? '—' }}</p>
        </div>
    </div>
</div>
