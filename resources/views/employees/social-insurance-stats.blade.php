@extends('layouts.app')

@section('title', '社会保険人数 - CE-Group 社員専用')

@section('content')
@php
    $badgeClasses = [
        'CE' => 'bg-sky-100 text-sky-800',
        'GT' => 'bg-rose-100 text-rose-800',
        'EM' => 'bg-emerald-100 text-emerald-800',
        'ME' => 'bg-amber-100 text-amber-900',
        'CEVN' => 'bg-violet-100 text-violet-800',
    ];
@endphp

<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <h1 class="text-2xl font-bold">社会保険人数（所属会社別）</h1>
    <div class="flex flex-wrap items-center gap-4 text-base">
        <a href="{{ route('employees.index') }}" class="text-blue-600 hover:underline">社員一覧へ</a>
        <a href="{{ route('dashboard') }}" class="text-blue-600 hover:underline">Top Page へ</a>
    </div>
</div>

<p class="mb-4 text-base text-slate-600">
    新規登録・詳細情報で「社会保険」にチェックした在籍社員を、所属会社ごとに集計しています。各社 {{ $threshold }} 人到達時に人事部・総務課へメール通知します。
</p>

<div class="overflow-hidden rounded-lg border border-slate-300 bg-white">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[40rem] border-collapse text-base">
            <thead>
                <tr class="border-b border-slate-300 bg-slate-50">
                    @foreach ($rows as $row)
                        <th class="min-w-[7rem] px-4 py-4 text-center align-middle">
                            <div class="flex flex-col items-center gap-2">
                                <span
                                    class="inline-flex min-w-[3.5rem] items-center justify-center rounded-full px-3 py-1 text-sm font-semibold {{ $badgeClasses[$row['code']] ?? 'bg-slate-100 text-slate-800' }}"
                                >
                                    {{ $row['code'] }}
                                </span>
                                <span class="text-xs font-normal leading-snug text-slate-500">{{ $row['company'] }}</span>
                            </div>
                        </th>
                    @endforeach
                    <th class="min-w-[7rem] px-4 py-4 text-center align-middle">
                        <div class="flex flex-col items-center gap-2">
                            <span class="inline-flex min-w-[3.5rem] items-center justify-center rounded-full bg-slate-200 px-3 py-1 text-sm font-semibold text-slate-800">
                                Total
                            </span>
                            <span class="text-xs font-normal text-slate-500">合計</span>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    @foreach ($rows as $row)
                        @if ($row['reached'])
                            <td class="border-l border-amber-200 bg-amber-50 px-4 py-6 text-center align-middle first:border-l-0">
                                <span class="text-3xl font-bold tabular-nums text-amber-800">{{ $row['count'] }}</span>
                                <span class="mt-1 block text-sm font-medium text-amber-800">人（到達）</span>
                            </td>
                        @else
                            <td class="border-l border-slate-200 px-4 py-6 text-center align-middle first:border-l-0">
                                <span class="text-3xl font-semibold tabular-nums text-slate-900">{{ $row['count'] }}</span>
                                <span class="mt-1 block text-sm text-slate-500">人</span>
                            </td>
                        @endif
                    @endforeach
                    <td class="border-l border-slate-200 bg-slate-50 px-4 py-6 text-center align-middle">
                        <span class="text-3xl font-bold tabular-nums text-slate-900">{{ $totalCount }}</span>
                        <span class="mt-1 block text-sm text-slate-500">人</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<p class="mt-3 text-sm text-slate-500">
    各社 {{ $threshold }} 人以上は強調表示されます。合計 {{ $totalCount }} 名。
</p>
@endsection
