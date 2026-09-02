@php
    use App\Models\User;
    use App\Support\HrDetailOrgFormState;
    use App\Support\RegistryOrgFormState;

    $suffix = $suffix ?? 'primary';
    $readonly = $readonly ?? false;
    $values = HrDetailOrgFormState::assignmentValues($hrDetail, $suffix);
    $org = RegistryOrgFormState::fromValues($values, splitSectionTeam: true);
    $departmentField = HrDetailOrgFormState::departmentField($suffix);
    $sectionField = HrDetailOrgFormState::sectionField($suffix);
    $teamField = HrDetailOrgFormState::teamField($suffix);
    $departmentOptions = User::registryDepartmentOptions($values['department'] !== '' ? $values['department'] : null);
@endphp

@include('partials.form-field', [
    'name' => $departmentField,
    'label' => HrDetailOrgFormState::departmentLabel($suffix),
    'type' => 'select',
    'options' => $departmentOptions,
    'value' => $values['department'],
    'readonly' => $readonly,
])

<div id="hr-detail-section-field-{{ $suffix }}">
    <label for="{{ $sectionField }}" class="block text-sm mb-1">{{ HrDetailOrgFormState::sectionLabel($suffix) }}</label>
    @if ($readonly)
        <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">{{ $values['section'] !== '' ? $values['section'] : '—' }}</p>
    @else
        <p id="hr-detail-section-hint-{{ $suffix }}" class="mb-1 text-xs text-slate-500 hidden" aria-hidden="true"></p>
        <select id="{{ $sectionField }}" name="{{ $sectionField }}" class="w-full rounded border border-slate-300 px-3 py-2 bg-white" @disabled($org['sectionSelectDisabled'])>
            <option value="">選択してください</option>
            @foreach ($org['sectionOptions'] as $sectionOption)
                <option value="{{ $sectionOption }}" @selected($org['selectedSection'] === $sectionOption)>{{ $sectionOption }}</option>
            @endforeach
        </select>
        @include('partials.field-error', ['field' => $sectionField])
    @endif
</div>

<div id="hr-detail-team-field-{{ $suffix }}" @class(['hidden' => ! $readonly && ! $org['showTeamField']])>
    <label for="{{ $teamField }}" class="block text-sm mb-1">{{ HrDetailOrgFormState::teamLabel($suffix) }}</label>
    @if ($readonly)
        <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">{{ $values['team'] !== '' ? $values['team'] : '—' }}</p>
    @else
        <p id="hr-detail-team-hint-{{ $suffix }}" class="mb-1 text-xs text-slate-500 hidden" aria-hidden="true"></p>
        <select id="{{ $teamField }}" name="{{ $teamField }}" class="w-full rounded border border-slate-300 px-3 py-2 bg-white" @disabled(! $org['showTeamField'] || $org['teamOptions'] === [])>
            <option value="">選択してください</option>
            @foreach ($org['teamOptions'] as $teamOption)
                <option value="{{ $teamOption }}" @selected($org['selectedTeam'] === $teamOption)>{{ $teamOption }}</option>
            @endforeach
        </select>
        @include('partials.field-error', ['field' => $teamField])
    @endif
</div>
