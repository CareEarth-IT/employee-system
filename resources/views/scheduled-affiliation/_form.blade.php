@php
    use App\Support\RegistryOrgAssignment;

    $schedule = $schedule ?? null;
    $formValues = $formValues ?? [];
    $orgSplit = RegistryOrgAssignment::splitForRegistryForm(
        $formValues['section'] ?? null,
        $formValues['department'] ?? null,
        $formValues['location'] ?? null,
    );
    $orgValues = [
        'company' => old('company', $formValues['company'] ?? null),
        'location' => old('location', $formValues['location'] ?? null),
        'department' => old('department', $formValues['department'] ?? null),
        'section' => old('section', $orgSplit['section']),
        'stored_section' => $formValues['section'] ?? null,
        'team' => old('team', $orgSplit['team']),
    ];
@endphp

<div class="space-y-4">
    <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        現行の所属を初期表示しています。変更した項目だけが反映日に適用され、変更していない項目は反映時点の現行所属のまま残ります。
    </div>

    <div>
        <label for="effective_date" class="mb-1 block text-base">反映日 <span class="text-red-600">*</span></label>
        <input
            id="effective_date"
            type="date"
            name="effective_date"
            value="{{ old('effective_date', $schedule?->effective_date?->format('Y-m-d')) }}"
            required
            class="w-full rounded border border-slate-300 px-3 py-2 text-base"
        >
        <p class="mt-1 text-sm text-slate-500">明日以降の日付を指定してください。当日になった時点で所属へ反映されます。</p>
        @include('partials.field-error', ['field' => 'effective_date'])
    </div>

    @include('partials.registry-org-fields', [
        'values' => $orgValues,
        'locationLabel' => '管轄',
        'departmentLabel' => '部',
        'splitSectionTeam' => true,
    ])

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label for="position" class="mb-1 block text-base">役職</label>
            <input
                id="position"
                name="position"
                value="{{ old('position', $formValues['position'] ?? null) }}"
                placeholder="例: 一般"
                class="w-full rounded border border-slate-300 px-3 py-2 text-base"
            >
            @include('partials.field-error', ['field' => 'position'])
        </div>
        <div>
            <label for="job_description" class="mb-1 block text-base">業務内容</label>
            <input
                id="job_description"
                name="job_description"
                value="{{ old('job_description', $formValues['job_description'] ?? null) }}"
                placeholder="例: WEB制作"
                class="w-full rounded border border-slate-300 px-3 py-2 text-base"
            >
            @include('partials.field-error', ['field' => 'job_description'])
        </div>
    </div>
</div>
