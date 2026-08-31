@php
    use App\Models\User;

    $formAction = $formAction ?? '';
    $formMethod = $formMethod ?? 'POST';
    $submitLabel = $submitLabel ?? '保存';
    $values = $values ?? [];
    $showPasswordRequired = $showPasswordRequired ?? false;
@endphp

<div class="space-y-4">
    <div>
        <label for="name" class="block text-sm mb-1">氏名 <span class="text-red-600">*</span></label>
        <input id="name" name="name" value="{{ $values['name'] ?? '' }}" required class="w-full rounded border border-slate-300 px-3 py-2">
        <p class="mt-1 text-xs text-slate-500">姓と名の間にスペースを入れてください（全角・半角どちらでも可。例: 山田 太郎、山田　太郎）</p>
        @include('partials.field-error', ['field' => 'name'])
    </div>

    <div>
        <label for="email" class="block text-sm mb-1">アドレス <span class="text-red-600">*</span></label>
        <input id="email" name="email" type="email" value="{{ $values['email'] ?? '' }}" required class="w-full rounded border border-slate-300 px-3 py-2">
        @include('partials.field-error', ['field' => 'email'])
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="password" class="block text-sm mb-1">
                パスワード
                @if ($showPasswordRequired)
                    <span class="text-red-600">*</span>
                @else
                    <span class="text-xs text-slate-500">（変更する場合のみ）</span>
                @endif
            </label>
            <input id="password" name="password" type="password" @required($showPasswordRequired) autocomplete="new-password" class="w-full rounded border border-slate-300 px-3 py-2">
            @include('partials.field-error', ['field' => 'password'])
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm mb-1">パスワード（確認）@if ($showPasswordRequired)<span class="text-red-600">*</span>@endif</label>
            <input id="password_confirmation" name="password_confirmation" type="password" @required($showPasswordRequired) autocomplete="new-password" class="w-full rounded border border-slate-300 px-3 py-2">
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="employee_id" class="block text-sm mb-1">社員ID <span class="text-red-600">*</span></label>
            <input
                id="employee_id"
                name="employee_id"
                value="{{ $values['employee_id'] ?? '' }}"
                required
                type="text"
                inputmode="numeric"
                autocomplete="off"
                pattern="[0-9]{5}"
                maxlength="5"
                class="w-full rounded border border-slate-300 px-3 py-2"
                oninput="this.value = this.value.replace(/\D/g, '').slice(0, 5)"
            >
            <p class="mt-1 text-xs text-slate-500">5桁の数字</p>
            @include('partials.field-error', ['field' => 'employee_id'])
        </div>
        <div>
            <label for="employment_type" class="block text-sm mb-1">雇用形態 <span class="text-red-600">*</span></label>
            <select id="employment_type" name="employment_type" required class="w-full rounded border border-slate-300 px-3 py-2 bg-white">
                <option value="">選択してください</option>
                @foreach (User::EMPLOYMENT_TYPE_OPTIONS as $type)
                    <option value="{{ $type }}" @selected(($values['employment_type'] ?? '') === $type)>{{ $type }}</option>
                @endforeach
            </select>
            @include('partials.field-error', ['field' => 'employment_type'])
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="department" class="block text-sm mb-1">所属部署 <span class="text-red-600">*</span></label>
            <input id="department" name="department" value="{{ $values['department'] ?? '' }}" required class="w-full rounded border border-slate-300 px-3 py-2">
            @include('partials.field-error', ['field' => 'department'])
        </div>
        <div>
            <label for="location" class="block text-sm mb-1">拠点 <span class="text-red-600">*</span></label>
            <select id="location" name="location" required class="w-full rounded border border-slate-300 px-3 py-2 bg-white">
                <option value="">選択してください</option>
                @foreach (User::OFFICE_LOCATIONS as $office)
                    <option value="{{ $office }}" @selected(($values['location'] ?? '') === $office)>{{ $office }}</option>
                @endforeach
            </select>
            @include('partials.field-error', ['field' => 'location'])
        </div>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
        {{ $submitLabel }}
    </button>
    <a href="{{ route('employees.index') }}" class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">社員一覧へ戻る</a>
</div>
