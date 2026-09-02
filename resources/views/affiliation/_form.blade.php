@php
    use App\Models\AffiliationHistory;
    use App\Models\User;

    $affiliation = $affiliation ?? null;
    $user = $user ?? $affiliation?->user;
    $defaultStartDate = old(
        'start_date',
        $affiliation?->start_date?->format('Y-m-d')
            ?? $user?->profile?->joined_at?->format('Y-m-d')
            ?? '',
    );
    $isCurrent = (bool) old(
        'is_current',
        $affiliation
            ? $affiliation->enrollment_status === AffiliationHistory::STATUS_ENROLLED
            : true
    );
    $lockCurrentOrgFields = ($affiliation?->isCurrent() ?? false)
        && ! auth()->user()->canEditCurrentAffiliationOrg();
    $orgValues = [
        'company' => old('company', $affiliation?->company),
        'location' => old('location', $affiliation?->location),
        'department' => old('department', $affiliation?->department),
        'section' => old('section', $affiliation?->section),
        'stored_section' => $affiliation?->section,
        'team' => old('team', ''),
    ];
    $lockedDisplay = [
        'department' => $affiliation?->department ?: '—',
        'section' => $affiliation?->section ?: '—',
    ];
@endphp

<div class="space-y-4">
    <div>
        <label class="block text-sm mb-1">社員ID <span class="text-xs text-slate-500">(自動取得)</span></label>
        <p class="w-full rounded border border-slate-200 bg-slate-50 px-3 py-2">{{ $user?->employee_id ?? '—' }}</p>
    </div>

    @include('partials.registry-org-fields', [
        'values' => $orgValues,
        'locationLabel' => '管轄',
        'departmentLabel' => '部',
        'lockOrgFields' => $lockCurrentOrgFields,
        'lockedDisplay' => $lockedDisplay,
    ])

    @if ($lockCurrentOrgFields)
        <p class="text-xs text-slate-500">現在の所属のため、部・課/チーム・期間の変更は人事部・情報システム部のみ可能です</p>
    @endif

    <div>
        <div class="flex items-center justify-between mb-2">
            <label class="block text-sm">期間</label>
            @if ($lockCurrentOrgFields)
                <span class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" checked disabled class="rounded border-slate-300">
                    在籍中
                </span>
            @else
                <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                    <input
                        type="checkbox"
                        id="is_current"
                        name="is_current"
                        value="1"
                        @checked($isCurrent)
                        class="rounded border-slate-300"
                    >
                    在籍中
                </label>
            @endif
        </div>
        @if ($lockCurrentOrgFields)
            <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                {{ $affiliation->start_date->format('Y-m-d') }} 〜 現在
            </p>
            <input type="hidden" name="is_current" value="1">
            <input type="hidden" name="start_date" value="{{ $affiliation->start_date->format('Y-m-d') }}">
        @else
            <div class="flex items-center gap-3">
                <input
                    id="start_date"
                    type="date"
                    name="start_date"
                    value="{{ $defaultStartDate }}"
                    required
                    class="flex-1 rounded border border-slate-300 px-3 py-2"
                >
                <span id="end-date-separator" class="text-slate-500 shrink-0 {{ $isCurrent ? 'hidden' : '' }}">～</span>
                <input
                    id="end_date"
                    type="date"
                    name="end_date"
                    value="{{ old('end_date', $affiliation?->end_date?->format('Y-m-d')) }}"
                    class="flex-1 rounded border border-slate-300 px-3 py-2 {{ $isCurrent ? 'hidden' : '' }}"
                >
            </div>
            <p id="current-hint" class="mt-1 text-xs text-slate-500 {{ $isCurrent ? '' : 'hidden' }}">在籍中のため終了日は不要です</p>
            @include('partials.field-error', ['field' => 'start_date'])
            @include('partials.field-error', ['field' => 'end_date'])
        @endif
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label for="position" class="block text-sm mb-1">役職</label>
            <input id="position" name="position" value="{{ old('position', $affiliation?->position) }}" placeholder="例: 一般" class="w-full rounded border border-slate-300 px-3 py-2">
            @include('partials.field-error', ['field' => 'position'])
        </div>
        <div>
            <label for="job_description" class="block text-sm mb-1">業務内容</label>
            <input id="job_description" name="job_description" value="{{ old('job_description', $affiliation?->job_description) }}" placeholder="例: WEB制作" class="w-full rounded border border-slate-300 px-3 py-2">
            @include('partials.field-error', ['field' => 'job_description'])
        </div>
    </div>
</div>

@unless ($lockCurrentOrgFields)
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const isCurrent = document.getElementById('is_current');
        const endDate = document.getElementById('end_date');
        const separator = document.getElementById('end-date-separator');
        const hint = document.getElementById('current-hint');

        if (!isCurrent || !endDate || !separator) return;

        const toggleEndDate = () => {
            const hidden = isCurrent.checked;
            endDate.classList.toggle('hidden', hidden);
            separator.classList.toggle('hidden', hidden);
            hint?.classList.toggle('hidden', !hidden);

            if (hidden) {
                endDate.value = '';
            }
        };

        isCurrent.addEventListener('change', toggleEndDate);
        toggleEndDate();
    });
</script>
@endpush
@endunless
