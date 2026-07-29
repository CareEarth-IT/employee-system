@extends(!empty($embed) ? 'layouts.embed' : 'layouts.app')

@section('title', '開発依頼詳細 - CE-Group 社員専用')

@section('content')
@php
    $managerByLabel = [
        '派遣開発' => 'カデアー',
        'PC/Wifi/スマホ' => '林、南谷',
        'その他' => '中元',
        '新規' => '中元',
        'Airtable' => 'カデアー',
    ];
    $embedQuery = !empty($embed) ? ['embed' => 1] : [];
@endphp
<div class="max-w-3xl mx-auto">
    @if (empty($embed))
    <div class="mb-4 flex flex-wrap items-center justify-center gap-2 text-sm">
        <a href="{{ route('development-requests.create', $embedQuery) }}" class="rounded border border-slate-300 bg-white px-4 py-2 text-slate-700 hover:bg-slate-50">新規依頼</a>
        <a href="{{ route('development-requests.index', $embedQuery) }}" class="rounded bg-blue-600 px-4 py-2 text-white">開発依頼内容一覧</a>
    </div>
    @else
    <div class="mb-3 px-1">
        <a href="{{ route('development-requests.index', $embedQuery) }}" class="text-sm text-[#1a56a0] hover:underline">← 一覧に戻る</a>
    </div>
    @endif

    <div class="bg-white border border-slate-300 rounded-lg overflow-hidden">
        <div class="border-b-2 border-blue-800 px-5 py-4">
            <h1 class="text-lg font-bold text-blue-800">開発依頼詳細 — ID {{ $request->request_number }}</h1>
            <p class="mt-1 text-xs text-slate-500">依頼日時: {{ $request->created_at?->format('Y/m/d H:i:s') ?? '—' }}</p>
        </div>

        @if (! $canEdit)
            <div class="mx-5 mt-4 rounded border-l-4 border-amber-500 bg-amber-50 px-3 py-2 text-xs text-slate-600">
                閲覧のみ（編集権限がありません）
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('development-requests.update', $request) }}"
            class="px-5 pt-2"
            @if ($canEdit) data-submitting-label="保存中..." @endif
        >
            @csrf
            @method('PUT')
            @if (!empty($embed))
                <input type="hidden" name="embed" value="1">
            @endif

            @php
                $rows = [
                    ['ID', $request->request_number],
                    ['依頼日', $request->request_date?->format('y/m/d')],
                    ['部署／課', $request->requester_department],
                    ['依頼者', $request->requester_name],
                    ['依頼者番号', $request->requester_number],
                ];
            @endphp

            @foreach ($rows as [$label, $value])
                <div class="flex gap-3 border-b border-slate-100 py-2.5">
                    <div class="w-40 shrink-0 text-xs text-slate-500 pt-1.5">{{ $label }}</div>
                    <div class="flex-1 text-sm leading-relaxed whitespace-pre-wrap break-words">{{ $value ?: '—' }}</div>
                </div>
            @endforeach

            <div class="flex gap-3 border-b border-slate-100 py-2.5">
                <div class="w-40 shrink-0 text-xs text-slate-500 pt-1.5">Type</div>
                <div class="flex-1">
                    @if ($canEdit)
                        <select id="content_type_label" name="content_type_label" class="max-w-xs w-full rounded border border-slate-300 px-3 py-1.5 text-sm bg-white">
                            @foreach ($typeLabels as $label)
                                <option value="{{ $label }}" @selected($request->contentTypeLabel() === $label)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @else
                        <div class="text-sm">{{ $request->contentTypeLabel() }}</div>
                    @endif
                </div>
            </div>

            <div class="flex gap-3 border-b border-slate-100 py-2.5">
                <div class="w-40 shrink-0 text-xs text-slate-500 pt-1.5">タイトル</div>
                <div class="flex-1 text-sm whitespace-pre-wrap break-words">{{ $request->title }}</div>
            </div>

            <div class="flex gap-3 border-b border-slate-100 py-2.5">
                <div class="w-40 shrink-0 text-xs text-slate-500 pt-1.5">進捗</div>
                <div class="flex-1">
                    @if ($canEdit)
                        <select name="progress" class="max-w-xs w-full rounded border border-slate-300 px-3 py-1.5 text-sm bg-white">
                            @foreach ($progressOptions as $option)
                                <option value="{{ $option }}" @selected(old('progress', $request->progress) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    @else
                        <div class="text-sm">{{ $request->progress }}</div>
                    @endif
                </div>
            </div>

            <div class="flex gap-3 border-b border-slate-100 py-2.5">
                <div class="w-40 shrink-0 text-xs text-slate-500 pt-1.5">備考</div>
                <div class="flex-1">
                    @if ($canEdit)
                        <textarea name="remarks" rows="3" class="w-full rounded border border-slate-300 px-3 py-1.5 text-sm">{{ old('remarks', $request->remarks) }}</textarea>
                    @else
                        <div class="text-sm whitespace-pre-wrap break-words">{{ $request->remarks ?: '—' }}</div>
                    @endif
                </div>
            </div>

            <div class="flex gap-3 border-b border-slate-100 py-2.5">
                <div class="w-40 shrink-0 text-xs text-slate-500 pt-1.5">予想工数(h)</div>
                <div class="flex-1">
                    @if ($canEdit)
                        <input type="number" name="estimated_hours" min="0" step="0.1" value="{{ old('estimated_hours', $request->estimated_hours) }}" class="max-w-xs w-full rounded border border-slate-300 px-3 py-1.5 text-sm" placeholder="0">
                    @else
                        <div class="text-sm">{{ $request->estimated_hours ?: '—' }}</div>
                    @endif
                </div>
            </div>

            <div class="flex gap-3 border-b border-slate-100 py-2.5">
                <div class="w-40 shrink-0 text-xs text-slate-500 pt-1.5">実工数(h)</div>
                <div class="flex-1">
                    @if ($canEdit)
                        <input type="number" name="actual_hours" min="0" step="0.1" value="{{ old('actual_hours', $request->actual_hours) }}" class="max-w-xs w-full rounded border border-slate-300 px-3 py-1.5 text-sm" placeholder="0">
                    @else
                        <div class="text-sm">{{ $request->actual_hours ?: '—' }}</div>
                    @endif
                </div>
            </div>

            <div class="flex gap-3 border-b border-slate-100 py-2.5">
                <div class="w-40 shrink-0 text-xs text-slate-500 pt-1.5">開発終了目標</div>
                <div class="flex-1">
                    @if ($canEdit)
                        <input type="date" name="development_target_date" value="{{ old('development_target_date', $request->development_target_date?->format('Y-m-d')) }}" class="max-w-xs w-full rounded border border-slate-300 px-3 py-1.5 text-sm">
                    @else
                        <div class="text-sm">{{ $request->development_target_date?->format('y/m/d') ?: '—' }}</div>
                    @endif
                </div>
            </div>

            <div class="flex gap-3 border-b border-slate-100 py-2.5">
                <div class="w-40 shrink-0 text-xs text-slate-500 pt-1.5">更新日</div>
                <div class="flex-1 text-sm">{{ $request->updated_at?->format('y/m/d') ?: '—' }}</div>
            </div>

            <div class="flex gap-3 border-b border-slate-100 py-2.5">
                <div class="w-40 shrink-0 text-xs text-slate-500 pt-1.5">開発担当</div>
                <div class="flex-1">
                    @if ($canEdit)
                        <select name="development_assignee" class="max-w-xs w-full rounded border border-slate-300 px-3 py-1.5 text-sm bg-white">
                            @foreach ($assigneeOptions as $option)
                                <option value="{{ $option }}" @selected(old('development_assignee', $request->development_assignee ?: '未') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    @else
                        <div class="text-sm">{{ $request->development_assignee ?: '未' }}</div>
                    @endif
                </div>
            </div>

            <div class="flex gap-3 border-b border-slate-100 py-2.5">
                <div class="w-40 shrink-0 text-xs text-slate-500 pt-1.5">管理者</div>
                <div id="managerValue" class="flex-1 text-sm">{{ $request->manager ?: '—' }}</div>
            </div>

            <div class="flex gap-3 border-b border-slate-100 py-2.5">
                <div class="w-40 shrink-0 text-xs text-slate-500 pt-1.5">目的 (改善内容)</div>
                <div class="flex-1 text-sm whitespace-pre-wrap break-words">{{ $request->purpose }}</div>
            </div>

            <div class="flex gap-3 border-b border-slate-100 py-2.5">
                <div class="w-40 shrink-0 text-xs text-slate-500 pt-1.5">依頼内容詳しく</div>
                <div class="flex-1 text-sm whitespace-pre-wrap break-words">{{ $request->detail }}</div>
            </div>

            @if ($canEdit)
                <div class="flex justify-end gap-3 py-5">
                    <button type="submit" class="rounded bg-blue-700 text-white px-8 py-2 text-sm hover:bg-blue-800">保存</button>
                </div>
            @else
                <div class="h-4"></div>
            @endif
        </form>
    </div>
</div>
@endsection

@if ($canEdit)
@push('scripts')
<script>
    (function () {
        var managers = @json($managerByLabel);
        var select = document.getElementById('content_type_label');
        var managerEl = document.getElementById('managerValue');
        if (!select || !managerEl) return;
        select.addEventListener('change', function () {
            managerEl.textContent = managers[select.value] || '増井';
        });
    })();
</script>
@endpush
@endif
