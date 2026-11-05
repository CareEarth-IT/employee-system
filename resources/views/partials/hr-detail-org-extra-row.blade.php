@php
    use App\Models\User;
    use App\Support\HrDetailOrgFormState;

    $index = $index ?? 0;
    $mark = $mark ?? HrDetailOrgFormState::indexMark((int) $index + 3);
    $row = $row ?? [];
    $positionOptions = $positionOptions ?? [];
    $location = $location ?? '';
    $idPrefix = 'org_extra_'.$index;
    $namePrefix = 'org_extra['.$index.']';
    $department = (string) ($row['department'] ?? '');
    $departmentOptions = User::registryDepartmentOptions(
        $department !== '' ? $department : null,
        $location !== '' ? $location : null,
    );
@endphp

<div class="hr-detail-org-extra-row grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-org-extra-row>
    <input type="hidden" name="{{ $namePrefix }}[affiliation_id]" value="{{ $row['affiliation_id'] ?? '' }}">
    <div>
        <label for="{{ $idPrefix }}_department" class="mb-1 block text-base">部署{{ $mark }}</label>
        <select id="{{ $idPrefix }}_department" name="{{ $namePrefix }}[department]" class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-base">
            <option value="">選択してください</option>
            @foreach ($departmentOptions as $departmentOption)
                <option value="{{ $departmentOption }}" @selected($department === $departmentOption)>{{ $departmentOption }}</option>
            @endforeach
        </select>
    </div>
    <div id="hr-detail-section-field-extra-{{ $index }}">
        <label for="{{ $idPrefix }}_section" class="mb-1 block text-base">課{{ $mark }}</label>
        <p id="hr-detail-section-hint-extra-{{ $index }}" class="mb-1 hidden text-sm text-slate-500" aria-hidden="true"></p>
        <select id="{{ $idPrefix }}_section" name="{{ $namePrefix }}[section]" class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-base">
            <option value="">選択してください</option>
            @if (($row['section'] ?? '') !== '')
                <option value="{{ $row['section'] }}" selected>{{ $row['section'] }}</option>
            @endif
        </select>
    </div>
    <div>
        <label for="{{ $idPrefix }}_position" class="mb-1 block text-base">役職{{ $mark }}</label>
        <select id="{{ $idPrefix }}_position" name="{{ $namePrefix }}[position]" class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-base">
            <option value="">選択してください</option>
            @foreach ($positionOptions as $positionOption)
                <option value="{{ $positionOption }}" @selected(($row['position'] ?? '') === $positionOption)>{{ $positionOption }}</option>
            @endforeach
        </select>
    </div>
    <div id="hr-detail-team-field-extra-{{ $index }}" class="hidden">
        <label for="{{ $idPrefix }}_team" class="mb-1 block text-base">チーム{{ $mark }}</label>
        <p id="hr-detail-team-hint-extra-{{ $index }}" class="mb-1 hidden text-sm text-slate-500" aria-hidden="true"></p>
        <select id="{{ $idPrefix }}_team" name="{{ $namePrefix }}[team]" class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-base">
            <option value="">選択してください</option>
            @if (($row['team'] ?? '') !== '')
                <option value="{{ $row['team'] }}" selected>{{ $row['team'] }}</option>
            @endif
        </select>
    </div>
    <div class="sm:col-span-2 lg:col-span-3">
        <button type="button" class="text-sm text-red-600 hover:underline" data-org-extra-remove>この部署を外す</button>
    </div>
</div>
