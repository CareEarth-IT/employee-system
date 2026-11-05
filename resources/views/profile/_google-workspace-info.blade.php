<div class="bg-white border border-slate-300 rounded-lg p-6 mb-6"
    @if ($canEditIdentity ?? false)
        data-profile-inline-edit
        data-update-url="{{ $identityUpdateUrl }}"
        data-csrf="{{ csrf_token() }}"
    @endif
>
    <h2 class="mb-4 text-lg font-bold">Google Workspace 連携情報</h2>
    @if ($canEditIdentity ?? false)
        <p class="mb-4 text-sm text-slate-500">新規登録ができる所属のみ編集可能です。社員ID・メールアドレス・姓・名をダブルクリックで編集できます。</p>
    @endif

    <div class="grid sm:grid-cols-2 gap-4">
        @include('profile.partials.inline-field', [
            'field' => 'employee_id',
            'label' => '社員ID',
            'value' => $user->employee_id ?? '',
            'editable' => $canEditIdentity ?? false,
        ])
        @include('profile.partials.inline-field', [
            'field' => 'email',
            'label' => 'メールアドレス',
            'type' => 'email',
            'value' => $user->email ?? '',
            'editable' => $canEditIdentity ?? false,
        ])
        @include('profile.partials.inline-field', [
            'field' => 'last_name',
            'label' => '姓',
            'value' => $user->last_name ?? '',
            'editable' => $canEditIdentity ?? false,
        ])
        @include('profile.partials.inline-field', [
            'field' => 'first_name',
            'label' => '名',
            'value' => $user->first_name ?? '',
            'editable' => $canEditIdentity ?? false,
        ])
    </div>
</div>
