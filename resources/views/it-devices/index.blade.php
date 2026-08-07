@extends('layouts.app')

@section('title', '情シスデバイス一覧 - CE-GR 社員専用')

@section('mainWidthClass', 'max-w-[96rem]')

@section('content')
@php
    $filterQuery = array_filter([
        'status' => $status,
        'keyword' => $keyword,
    ], fn ($value) => $value !== '');

    $tabQuery = fn (string $tabStatus) => array_filter([
        'status' => $tabStatus,
        'keyword' => $keyword,
    ], fn ($value) => $value !== '');
@endphp

<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <h1 class="text-xl font-bold">情シスデバイス一覧</h1>
    <div class="flex flex-wrap items-center gap-4 text-sm">
        <a href="{{ route('dashboard') }}" class="text-blue-600 hover:underline">Top Page へ</a>
    </div>
</div>

<p class="mb-4 text-sm text-slate-600">
    詳細情報の IT・デバイス項目と、社員の基本情報を一覧表示します。名前をクリックすると IT・デバイス情報を表示します。
</p>

@if (session('success'))
    <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
        {{ session('success') }}
    </div>
@endif

<form method="GET" action="{{ route('it-devices.index') }}" class="mb-4 rounded-lg border border-slate-300 bg-white p-4">
    <input type="hidden" name="status" value="{{ $status }}">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="keyword" class="mb-1 block text-sm">キーワード</label>
            <input
                type="text"
                id="keyword"
                name="keyword"
                value="{{ $keyword }}"
                maxlength="100"
                placeholder="氏名・メール・社員ID・部署・拠点など"
                class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                検索
            </button>
            @if ($keyword !== '')
                <a href="{{ route('it-devices.index', ['status' => $status]) }}" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    クリア
                </a>
            @endif
        </div>
    </div>
</form>

<div class="overflow-hidden rounded-lg border border-slate-300 bg-white">
    <nav class="flex border-b border-slate-200 bg-slate-50" aria-label="状況タブ">
        @foreach ($statusTabs as $tabStatus)
            <a
                href="{{ route('it-devices.index', $tabQuery($tabStatus)) }}"
                @class([
                    'border-b-2 -mb-px px-4 py-3 text-sm font-medium transition-colors',
                    'border-blue-600 bg-white text-blue-700' => $status === $tabStatus,
                    'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900' => $status !== $tabStatus,
                ])
            >
                {{ $tabStatus }}
            </a>
        @endforeach
    </nav>

    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        {{ count($rows) }}名
        <span class="text-slate-500">／ 状況: {{ $status }}</span>
        @if ($keyword !== '')
            <span class="text-slate-500">／ 「{{ $keyword }}」</span>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="whitespace-nowrap px-3 py-3">社員ID</th>
                    <th class="whitespace-nowrap px-3 py-3">名前</th>
                    <th class="px-3 py-3">メールアドレス</th>
                    <th class="whitespace-nowrap px-3 py-3">拠点</th>
                    <th class="whitespace-nowrap px-3 py-3">部署</th>
                    <th class="whitespace-nowrap px-3 py-3">状況</th>
                    <th class="whitespace-nowrap px-3 py-3">雇用形態</th>
                    <th class="whitespace-nowrap px-3 py-3">電話番号</th>
                    <th class="whitespace-nowrap px-3 py-3">PC</th>
                    <th class="whitespace-nowrap px-3 py-3">モバイル</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="whitespace-nowrap px-3 py-3 align-top">{{ $row['employee_id'] }}</td>
                        <td class="px-3 py-3 align-top">
                            <button
                                type="button"
                                class="it-device-name break-words text-left text-blue-600 hover:underline"
                                data-detail-url="{{ $row['detail_url'] }}?status={{ urlencode($status) }}&keyword={{ urlencode($keyword) }}"
                            >
                                {{ $row['name'] }}
                            </button>
                        </td>
                        <td class="px-3 py-3 align-top">
                            @if ($row['email'] !== '—')
                                <a href="mailto:{{ $row['email'] }}" class="break-all text-blue-600 hover:underline">{{ $row['email'] }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 align-top">{{ $row['location'] }}</td>
                        <td class="px-3 py-3 align-top">{{ $row['department'] }}</td>
                        <td class="whitespace-nowrap px-3 py-3 align-top">{{ $row['employment_status'] }}</td>
                        <td class="whitespace-nowrap px-3 py-3 align-top">{{ $row['employment_type'] }}</td>
                        <td class="whitespace-nowrap px-3 py-3 align-top">{{ $row['phone'] }}</td>
                        <td class="whitespace-nowrap px-3 py-3 align-top text-center">
                            @if ($row['has_pc'] === null)
                                —
                            @else
                                <input type="checkbox" disabled @checked($row['has_pc']) class="rounded border-slate-300" aria-label="PC">
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 align-top text-center">
                            @if ($row['has_mobile'] === null)
                                —
                            @else
                                <input type="checkbox" disabled @checked($row['has_mobile']) class="rounded border-slate-300" aria-label="モバイル">
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-slate-400">
                            @if ($keyword !== '')
                                検索条件に該当する社員はいません
                            @else
                                社員が登録されていません
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div
    id="it-device-modal"
    class="fixed inset-0 z-50 hidden items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="it-device-modal-title"
>
    <div id="it-device-backdrop" class="absolute inset-0 bg-slate-900/50"></div>

    <div class="relative max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-lg border border-slate-300 bg-white p-6 shadow-xl sm:p-8">
        <button
            type="button"
            id="it-device-close"
            class="absolute top-3 right-3 text-xl leading-none text-slate-400 hover:text-slate-600"
            aria-label="閉じる"
        >
            &times;
        </button>

        <h2 id="it-device-modal-title" class="sr-only">IT・デバイス</h2>
        <div id="it-device-panel" class="min-w-0">
            <p class="text-sm text-slate-500">読み込み中…</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('it-device-modal');
        const panel = document.getElementById('it-device-panel');
        const closeBtn = document.getElementById('it-device-close');
        const backdrop = document.getElementById('it-device-backdrop');
        let lastOpener = null;

        if (!modal || !panel) return;

        const closeModal = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            panel.innerHTML = '<p class="text-sm text-slate-500">読み込み中…</p>';
            lastOpener?.focus();
            lastOpener = null;
        };

        const openModal = async (button) => {
            const url = button.dataset.detailUrl;
            if (!url) return;

            lastOpener = button;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            panel.innerHTML = '<p class="text-sm text-slate-500">読み込み中…</p>';

            try {
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'text/html',
                    },
                });

                if (!response.ok) {
                    throw new Error('failed');
                }

                panel.innerHTML = await response.text();
                closeBtn?.focus();
            } catch (error) {
                panel.innerHTML = '<p class="text-sm text-red-700">IT・デバイス情報を読み込めませんでした。</p>';
            }
        };

        document.querySelectorAll('.it-device-name').forEach((button) => {
            button.addEventListener('click', () => openModal(button));
        });

        closeBtn?.addEventListener('click', closeModal);
        backdrop?.addEventListener('click', closeModal);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('flex')) {
                closeModal();
            }
        });
    });
</script>
@endpush
@endsection
