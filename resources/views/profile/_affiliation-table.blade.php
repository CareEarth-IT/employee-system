@php
    use App\Support\RegistryOrgAssignment;
    use App\Support\UserRouteHelper;
@endphp

<div class="mt-8 bg-white border border-slate-300 rounded-lg p-6">
    @if ($editable ?? false)
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold">所属部署</h2>
            <a href="{{ UserRouteHelper::route($user, 'affiliations.create', 'users.affiliations.create') }}" class="rounded bg-slate-700 text-white px-4 py-2 text-sm hover:bg-slate-800">
                登録はこちら
            </a>
        </div>
    @else
        <h2 class="font-bold mb-4">所属部署</h2>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="border-b border-slate-200 text-left text-slate-500">
                    @if ($editable ?? false)
                        <th class="py-2 pr-2"></th>
                    @endif
                    <th class="py-2 pr-4">期間</th>
                    <th class="py-2 pr-4">所属会社</th>
                    <th class="py-2 pr-4">管轄</th>
                    <th class="py-2 pr-4">部</th>
                    <th class="py-2 pr-4">課</th>
                    <th class="py-2 pr-4">チーム</th>
                    <th class="py-2 pr-4">役職</th>
                    <th class="py-2">業務内容</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($user->affiliationHistories as $history)
                    @php
                        $orgParts = RegistryOrgAssignment::splitForRegistryForm(
                            $history->section,
                            $history->department,
                            $history->location,
                        );
                    @endphp
                    <tr class="border-b border-slate-100">
                        @if ($editable ?? false)
                            <td class="py-2 pr-2">
                                <a href="{{ route('affiliations.edit', $history) }}" class="text-blue-600 hover:underline">編集</a>
                            </td>
                        @endif
                        <td class="py-2 pr-4">
                            {{ $history->start_date->format('Y-m-d') }}
                            〜
                            {{ $history->end_date?->format('Y-m-d') ?? '現在' }}
                        </td>
                        <td class="py-2 pr-4">{{ $history->company ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ $history->location ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ $history->department ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ $orgParts['section'] ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ $orgParts['team'] ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ $history->position ?? '—' }}</td>
                        <td class="py-2">{{ $history->job_description ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ ($editable ?? false) ? 9 : 8 }}" class="py-4 text-center text-slate-400">所属部署が登録されていません</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
