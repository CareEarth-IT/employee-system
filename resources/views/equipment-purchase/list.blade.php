@extends('layouts.app')

@section('title', '備品購入 一覧 - CE-Group 社員専用')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold">備品購入 一覧</h1>
            @if ($showsOnlyOwnApplications ?? false)
                <p class="text-sm text-slate-600 mt-1">自分が送信した申請のみ表示されます</p>
            @endif
        </div>
        <a href="{{ route('equipment-purchases.index') }}" class="text-sm text-blue-600 hover:underline shrink-0">備品購入トップへ</a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <form method="GET" action="{{ route('equipment-purchases.list') }}" class="bg-white border border-slate-300 rounded-lg p-6 mb-6 space-y-4">
        <div class="flex flex-wrap items-end gap-4">
            <div class="min-w-[10rem] flex-1">
                <label for="keyword" class="block text-sm mb-1">キーワード検索</label>
                <input
                    id="keyword"
                    type="text"
                    name="keyword"
                    value="{{ $filters['keyword'] }}"
                    placeholder="商品名・申請者・メモなど"
                    class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                >
            </div>

            <div class="min-w-[10rem]">
                <label for="department" class="block text-sm mb-1">部門別検索</label>
                <select id="department" name="department" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    <option value="">すべて</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept }}" @selected($filters['department'] === $dept)>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[10rem]">
                <label for="location" class="block text-sm mb-1">拠点別検索</label>
                <select id="location" name="location" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    <option value="">すべて</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc }}" @selected($filters['location'] === $loc)>{{ $loc }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm mb-1">期間検索</label>
                <div class="flex items-center gap-2">
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="rounded border border-slate-300 px-3 py-2 text-sm">
                    <span class="text-slate-500">〜</span>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="rounded border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm mb-1">金額検索（税込）</label>
                <div class="flex items-center gap-2">
                    <input type="number" name="price_min" min="0" value="{{ $filters['price_min'] }}" placeholder="下限" class="w-28 rounded border border-slate-300 px-3 py-2 text-sm">
                    <span class="text-slate-500">〜</span>
                    <input type="number" name="price_max" min="0" value="{{ $filters['price_max'] }}" placeholder="上限" class="w-28 rounded border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>

            <button type="submit" class="rounded bg-blue-600 text-white px-6 py-2 text-sm font-medium hover:bg-blue-700">
                検索
            </button>
        </div>
    </form>

    <div class="bg-white border border-slate-300 rounded-lg overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-slate-300 bg-slate-100 text-sm font-semibold text-slate-900">
            結果一覧
        </div>

        <div class="overflow-x-auto bg-white">
            <table class="w-full text-sm">
                <thead class="bg-slate-100 border-b border-slate-300">
                    <tr class="text-left text-slate-900 font-semibold">
                        <th class="px-3 py-2 whitespace-nowrap">備品申請ID</th>
                        <th class="px-3 py-2 whitespace-nowrap">申請日</th>
                        <th class="px-3 py-2 whitespace-nowrap">承認結果</th>
                        <th class="px-3 py-2 whitespace-nowrap">利用部署 / 課</th>
                        <th class="px-3 py-2 whitespace-nowrap text-right">購入額</th>
                        <th class="px-3 py-2 whitespace-nowrap">申請者</th>
                        <th class="px-3 py-2 whitespace-nowrap">商品名</th>
                        <th class="px-3 py-2 whitespace-nowrap">メモ</th>
                        <th class="px-3 py-2 whitespace-nowrap">注文日</th>
                        <th class="px-3 py-2 whitespace-nowrap text-center">領収書あり</th>
                        @if ($canEditConsumable ?? false)
                            <th class="px-3 py-2 whitespace-nowrap text-center">消耗品</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applications as $application)
                        <tr @class([
                            'border-b border-slate-200',
                            'equipment-purchase-row-receipt' => $application->receipt_issued,
                            'bg-white hover:bg-slate-50' => ! $application->receipt_issued,
                        ])>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <a href="{{ $application->detailUrl() }}" class="text-blue-600 hover:underline">{{ $application->id }}</a>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $application->application_date->format('Y/m/d') }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <a href="{{ $application->detailUrl() }}" class="{{ $application->approvalStatusColorClass() }} hover:underline font-medium">
                                    {{ $application->approvalStatusIcon() }}{{ $application->approvalStatusLabel() }}
                                </a>
                            </td>
                            <td class="px-3 py-2">{{ $application->listDepartmentLabel() }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ number_format($application->price_including_tax) }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $application->user->displayName() }}</td>
                            <td class="px-3 py-2 text-xs max-w-[10rem]">{{ Str::limit($application->product_name, 25) }}</td>
                            <td class="px-3 py-2 text-xs max-w-[10rem]">{{ Str::limit($application->listMemo(), 25) ?: '—' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $application->orderDateDisplay() ?? '—' }}</td>
                            <td class="px-3 py-2 text-center whitespace-nowrap">
                                @if ($application->receipt_issued)
                                    <span class="font-medium" title="領収書発行済">✓</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            @if ($canEditConsumable ?? false)
                                <td class="px-3 py-2 text-center whitespace-nowrap">
                                    <form
                                        method="POST"
                                        action="{{ route('equipment-purchases.consumable.update', $application) }}"
                                        class="inline-flex justify-center"
                                    >
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="is_consumable" value="0">
                                        <input
                                            type="checkbox"
                                            name="is_consumable"
                                            value="1"
                                            @checked($application->is_consumable)
                                            onchange="this.form.submit()"
                                            class="rounded border-slate-300 cursor-pointer"
                                            aria-label="消耗品"
                                        >
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ($canEditConsumable ?? false) ? 11 : 10 }}" class="px-4 py-8 text-center text-slate-400">該当する申請はありません</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($applications->hasPages())
            <div class="px-4 py-3 border-t border-slate-300 bg-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-slate-600">
                <p>{{ $applications->currentPage() }}ページ目表示 / {{ $applications->perPage() }}件ずつ</p>
                <div class="flex flex-wrap gap-1">
                    @foreach ($applications->getUrlRange(max(1, $applications->currentPage() - 4), min($applications->lastPage(), $applications->currentPage() + 5)) as $page => $url)
                        <a
                            href="{{ $url }}"
                            class="inline-flex min-w-[2rem] items-center justify-center rounded border px-2 py-1 {{ $page === $applications->currentPage() ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-300 hover:bg-slate-50' }}"
                        >
                            {{ $page }}
                        </a>
                    @endforeach
                </div>
            </div>
        @else
            <div class="px-4 py-3 border-t border-slate-300 bg-slate-100 text-sm text-slate-600">
                1ページ目表示 / {{ $applications->perPage() }}件ずつ
            </div>
        @endif
    </div>

    <div class="bg-white border border-slate-300 rounded-lg p-6">
        <a
            href="{{ route('equipment-purchases.export', request()->query()) }}"
            class="inline-block rounded bg-blue-600 text-white px-6 py-2.5 text-sm font-medium hover:bg-blue-700"
        >
            検索結果の{{ number_format($totalCount) }}件を CSV 書出す
        </a>
        <p class="mt-3 text-xs text-slate-500">
            CSV書き出しはページに関係なく検索結果のすべての対象情報を書き出します
        </p>
    </div>
</div>
@endsection
