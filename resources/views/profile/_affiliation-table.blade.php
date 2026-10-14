@php
    use App\Support\RegistryOrgAssignment;
    use App\Support\ScheduledAffiliationChangeDiff;
    use App\Support\UserRouteHelper;
@endphp

<div class="mt-8 bg-white border border-slate-300 rounded-lg p-6">
    @if ($editable ?? false)
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold">所属部署</h2>
            <a href="{{ UserRouteHelper::route($user, 'affiliations.create', 'users.affiliations.create') }}" class="rounded bg-slate-700 px-4 py-2 text-base text-white hover:bg-slate-800">
                登録はこちら
            </a>
        </div>
    @else
        <h2 class="mb-4 text-lg font-bold">所属部署</h2>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-base">
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
                            {{ $history->start_date?->format('Y-m-d') ?? '—' }}
                            〜
                            @if ($history->isCurrent())
                                現在
                            @else
                                {{ $history->end_date?->format('Y-m-d') ?? '—' }}
                            @endif
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

    @if (auth()->user()?->canScheduleAffiliationChange())
        @php
            $pendingSchedule = $user->pendingScheduledAffiliationChange();
            $pendingChanges = $pendingSchedule
                ? ScheduledAffiliationChangeDiff::changedSummaries($pendingSchedule)
                : [];
        @endphp
        <div class="mt-6 border-t border-slate-200 pt-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-bold text-slate-800">異動・役職の事前登録</h3>
                @if ($pendingSchedule === null)
                    <a
                        href="{{ route('users.scheduled-affiliations.create', $user) }}"
                        class="rounded border border-slate-400 px-3 py-1.5 text-sm hover:bg-slate-50"
                    >
                        事前登録する
                    </a>
                @endif
            </div>

            @if ($pendingSchedule)
                <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <p class="font-medium">
                        {{ $pendingSchedule->effective_date?->format('Y-m-d') }} 反映予定
                        <span class="ml-2 font-normal text-slate-500">（変更箇所のみ反映・それ以外は現行のまま）</span>
                    </p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @forelse ($pendingChanges as $change)
                            <li>{{ $change['label'] }}: {{ $change['value'] }}</li>
                        @empty
                            <li>変更項目なし</li>
                        @endforelse
                    </ul>
                    <div class="mt-3 flex flex-wrap gap-4">
                        <a href="{{ route('scheduled-affiliations.edit', $pendingSchedule) }}" class="text-blue-600 hover:underline">編集</a>
                        <form method="POST" action="{{ route('scheduled-affiliations.destroy', $pendingSchedule) }}" onsubmit="return confirm('この事前登録を取り消しますか？');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">取消</button>
                        </form>
                    </div>
                </div>
            @else
                <p class="text-sm text-slate-500">反映日を指定して役職変更・部署異動を予約できます。変更した項目だけが反映され、それ以外は現行所属のままです。</p>
            @endif
        </div>
    @endif
</div>
