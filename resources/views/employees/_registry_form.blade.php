@php
    use App\Models\EmployeeHrDetail;
    use App\Models\User;

    $formAction = $formAction ?? '';
    $formMethod = $formMethod ?? 'POST';
    $submitLabel = $submitLabel ?? '保存';
    $values = $values ?? [];
    $showPasswordRequired = $showPasswordRequired ?? false;
    $showPasswordFields = $showPasswordFields ?? true;
    $splitSectionTeam = $splitSectionTeam ?? false;
    $sectionRequiredForGr = $sectionRequiredForGr ?? false;
    $employmentStatusOptions = User::employmentStatusOptions($values['employment_status'] ?? null);
    $selectedEmploymentStatus = $values['employment_status'] ?? '在籍';
@endphp

<div class="space-y-4">
    <div>
        <label for="email" class="block text-sm mb-1">アドレス <span class="text-red-600">*</span></label>
        <input id="email" name="email" type="email" value="{{ $values['email'] ?? '' }}" required class="w-full rounded border border-slate-300 px-3 py-2">
        @include('partials.field-error', ['field' => 'email'])
    </div>

    @if ($showPasswordFields)
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
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="name" class="block text-sm mb-1">名前 <span class="text-red-600">*</span></label>
            <p class="mb-1 text-xs text-slate-500">名簿上の名前、漢字orカナ<br>（パスポート等身分証上の表記に準ずる）</p>
            <input id="name" name="name" value="{{ $values['name'] ?? '' }}" required class="w-full rounded border border-slate-300 px-3 py-2">
            @include('partials.field-error', ['field' => 'name'])
        </div>
        <div>
            <label for="name_kana" class="block text-sm mb-1">ナマエ</label>
            <p class="mb-1 text-xs text-slate-500">名簿上の名前、カタカナ表記<br>（全国籍共通）</p>
            <input id="name_kana" name="name_kana" value="{{ $values['name_kana'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2">
            @include('partials.field-error', ['field' => 'name_kana'])
        </div>
        <div>
            <label for="english_name" class="block text-sm mb-1">Name</label>
            <p class="mb-1 text-xs text-slate-500">名簿上の名前、ローマ字or英字<br>（全国籍共通）</p>
            <input id="english_name" name="english_name" value="{{ $values['english_name'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2">
            @include('partials.field-error', ['field' => 'english_name'])
        </div>
        <div>
            <label for="birth_date" class="block text-sm mb-1">生年月日</label>
            <p class="mb-1 text-xs text-slate-500 invisible select-none" aria-hidden="true">名簿上の名前、ローマ字or英字<br>（全国籍共通）</p>
            <input id="birth_date" name="birth_date" type="date" value="{{ $values['birth_date'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2">
            @include('partials.field-error', ['field' => 'birth_date'])
        </div>
        <div>
            <label for="gender" class="block text-sm mb-1">性別</label>
            <select id="gender" name="gender" class="w-full rounded border border-slate-300 px-3 py-2 bg-white">
                <option value="">選択してください</option>
                @foreach (EmployeeHrDetail::GENDERS as $gender)
                    <option value="{{ $gender }}" @selected(($values['gender'] ?? '') === $gender)>{{ $gender }}</option>
                @endforeach
            </select>
            @include('partials.field-error', ['field' => 'gender'])
        </div>
        <div>
            <label for="nationality" class="block text-sm mb-1">国籍</label>
            @include('partials.nationality-select', ['selected' => $values['nationality'] ?? ''])
            @include('partials.field-error', ['field' => 'nationality'])
        </div>
        <div>
            <label for="employee_id" class="block text-sm mb-1">ID <span class="text-red-600">*</span></label>
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
        <div>
            <label for="employment_status" class="block text-sm mb-1">状況 <span class="text-red-600">*</span></label>
            <select id="employment_status" name="employment_status" required class="w-full rounded border border-slate-300 px-3 py-2 bg-white">
                @foreach ($employmentStatusOptions as $statusOption)
                    <option value="{{ $statusOption }}" @selected($selectedEmploymentStatus === $statusOption)>{{ $statusOption }}</option>
                @endforeach
            </select>
            @include('partials.field-error', ['field' => 'employment_status'])
        </div>
        @include('partials.registry-org-fields', [
            'values' => $values,
            'embedded' => true,
            'locationLabel' => '管轄',
            'departmentLabel' => '部署',
            'companyRequired' => true,
            'locationRequired' => true,
            'departmentRequired' => true,
            'splitSectionTeam' => $splitSectionTeam,
            'sectionRequiredForGr' => $sectionRequiredForGr,
        ])
        <div class="sm:col-span-2 space-y-4">
            <div>
                <label for="joined_at" class="block text-sm mb-1">入社予定日(目安)</label>
                <p class="mb-1 text-xs text-slate-500">未確定の場合は備考欄にその旨記載</p>
                <input id="joined_at" name="joined_at" type="date" value="{{ $values['joined_at'] ?? '' }}" class="w-full rounded border border-slate-300 px-3 py-2 sm:max-w-[calc(50%-0.5rem)]">
                @include('partials.field-error', ['field' => 'joined_at'])
            </div>
            <div>
                <label for="remarks" class="block text-sm mb-1">備考</label>
                <textarea id="remarks" name="remarks" rows="3" class="w-full rounded border border-slate-300 px-3 py-2">{{ $values['remarks'] ?? '' }}</textarea>
                @include('partials.field-error', ['field' => 'remarks'])
            </div>
        </div>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
        {{ $submitLabel }}
    </button>
    <a href="{{ route('employees.index') }}" class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">社員一覧へ戻る</a>
</div>
