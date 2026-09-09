@php
    use App\Models\User;
    use App\Support\RegistryGrAssignment;
    use App\Support\RegistryOrgFormState;

    $values = $values ?? [];
    $locationLabel = $locationLabel ?? '管轄';
    $departmentLabel = $departmentLabel ?? '部署';
    $companyRequired = $companyRequired ?? false;
    $locationRequired = $locationRequired ?? false;
    $departmentRequired = $departmentRequired ?? false;
    $lockOrgFields = $lockOrgFields ?? false;
    $lockedDisplay = $lockedDisplay ?? [];
    $splitSectionTeam = $splitSectionTeam ?? false;
    $sectionRequiredForGr = $sectionRequiredForGr ?? false;
    $embedded = $embedded ?? false;
    $org = RegistryOrgFormState::fromValues($values, $splitSectionTeam);
    $sectionIsRequired = $sectionRequiredForGr && $org['selectedDepartment'] === RegistryGrAssignment::DEPARTMENT;
    $sectionLabel = $splitSectionTeam ? '課' : '課/チーム';
@endphp

<div @class([
    'grid gap-4 sm:grid-cols-2' => ! $embedded,
    'contents' => $embedded,
    $wrapperClass ?? '' => ! $embedded && ($wrapperClass ?? '') !== '',
])>
    <div>
        <label for="company" class="mb-1 block text-base">所属会社 @if ($companyRequired)<span class="text-red-600">*</span>@endif</label>
        <select id="company" name="company" @required($companyRequired) class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-base">
            <option value="">選択してください</option>
            @foreach ($org['companyOptions'] as $companyOption)
                <option value="{{ $companyOption }}" @selected($org['selectedCompany'] === $companyOption)>{{ $companyOption }}</option>
            @endforeach
        </select>
        @include('partials.field-error', ['field' => 'company'])
    </div>
    <div>
        <label for="location" class="mb-1 block text-base">{{ $locationLabel }} @if ($locationRequired)<span class="text-red-600">*</span>@endif</label>
        <select id="location" name="location" @required($locationRequired) class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-base">
            <option value="">選択してください</option>
            @foreach (User::OFFICE_LOCATIONS as $office)
                <option value="{{ $office }}" @selected($org['selectedLocation'] === $office)>{{ $office }}</option>
            @endforeach
        </select>
        @include('partials.field-error', ['field' => 'location'])
    </div>
    <div>
        <label for="department" class="mb-1 block text-base">{{ $departmentLabel }} @if ($departmentRequired)<span class="text-red-600">*</span>@endif</label>
        @if ($lockOrgFields)
            <p class="w-full rounded border border-slate-200 bg-slate-50 px-3 py-2 text-base">{{ $lockedDisplay['department'] ?? '—' }}</p>
            <input type="hidden" name="department" value="{{ $values['department'] ?? '' }}">
        @else
            <select id="department" name="department" @required($departmentRequired) class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-base">
                <option value="">選択してください</option>
                @foreach ($org['departmentOptions'] as $departmentOption)
                    <option value="{{ $departmentOption }}" @selected($org['selectedDepartment'] === $departmentOption)>{{ $departmentOption }}</option>
                @endforeach
            </select>
            @include('partials.field-error', ['field' => 'department'])
        @endif
    </div>
    <div id="registry-section-field">
        <label for="section" class="mb-1 block text-base">{{ $sectionLabel }} @if ($sectionRequiredForGr)<span id="registry-section-required-mark" @class(['text-red-600', 'hidden' => ! $sectionIsRequired])>*</span>@endif</label>
        @if ($lockOrgFields)
            <p class="w-full rounded border border-slate-200 bg-slate-50 px-3 py-2 text-base">{{ $lockedDisplay['section'] ?? '—' }}</p>
            <input type="hidden" name="section" value="{{ $values['stored_section'] ?? ($values['section'] ?? '') }}">
        @else
            <p id="registry-section-hint" class="mb-1 text-xs text-slate-500 hidden" aria-hidden="true"></p>
            <select id="section" name="section" class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-base" @disabled($org['sectionSelectDisabled']) @required($sectionIsRequired && ! $org['sectionSelectDisabled'])>
                <option value="">選択してください</option>
                @foreach ($org['sectionOptions'] as $sectionOption)
                    <option value="{{ $sectionOption }}" @selected($org['selectedSection'] === $sectionOption)>{{ $sectionOption }}</option>
                @endforeach
            </select>
            @include('partials.field-error', ['field' => 'section'])
        @endif
    </div>
    @if ($splitSectionTeam)
    <div id="registry-team-field" @class(['hidden' => ! $lockOrgFields && ! $org['showTeamField']])>
        <label for="team" class="mb-1 block text-base">チーム</label>
        @if ($lockOrgFields)
            <p class="w-full rounded border border-slate-200 bg-slate-50 px-3 py-2 text-base">{{ $lockedDisplay['team'] ?? '—' }}</p>
        @else
            <p id="registry-team-hint" class="mb-1 text-xs text-slate-500 hidden" aria-hidden="true"></p>
            <select id="team" name="team" class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-base" @disabled(! $org['showTeamField'] || $org['teamOptions'] === [])>
                <option value="">選択してください</option>
                @foreach ($org['teamOptions'] as $teamOption)
                    <option value="{{ $teamOption }}" @selected($org['selectedTeam'] === $teamOption)>{{ $teamOption }}</option>
                @endforeach
            </select>
            @include('partials.field-error', ['field' => 'team'])
        @endif
    </div>
    @endif
</div>

@if (! $lockOrgFields)
    @include('partials.registry-org-fields-script', [
        'selectedSection' => $org['selectedSection'],
        'selectedTeam' => $org['selectedTeam'],
        'splitSectionTeam' => $splitSectionTeam,
        'sectionRequiredForGr' => $sectionRequiredForGr,
        'departmentRequired' => $departmentRequired,
    ])
@endif
