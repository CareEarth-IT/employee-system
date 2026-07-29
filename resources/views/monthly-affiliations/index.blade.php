@extends('layouts.app')

@section('title', '月次所属記録 - CE-Group 社員専用')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-bold">月次所属記録</h1>
    <a href="{{ route('dashboard') }}" class="text-sm text-blue-600 hover:underline">Top Page へ</a>
</div>

@if (session('success'))
    <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-green-800 text-sm">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm">
        {{ session('error') }}
    </div>
@endif

<div class="bg-white border border-slate-300 rounded-lg p-6 mb-6">
    <h2 class="font-bold mb-2">今月の所属情報を保存</h2>
    <p class="text-sm text-slate-600 mb-4">
        全社員の氏名・社員ID・拠点・部・課を {{ \App\Models\MonthlyAffiliationRecord::formatYearMonthLabel($currentMonth) }} 分として保存します。
    </p>

    <form method="POST" action="{{ route('monthly-affiliations.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="year_month" value="{{ $currentMonth }}">

        @if ($currentMonthSaved)
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="overwrite" value="1" class="rounded border-slate-300">
                既存の {{ \App\Models\MonthlyAffiliationRecord::formatYearMonthLabel($currentMonth) }} データを上書きする
            </label>
        @endif

        <button type="submit" class="rounded bg-blue-600 text-white px-6 py-2 text-sm font-medium hover:bg-blue-700">
            {{ \App\Models\MonthlyAffiliationRecord::formatYearMonthLabel($currentMonth) }} を保存
        </button>
    </form>
</div>

<div class="bg-white border border-slate-300 rounded-lg overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-200 bg-slate-50 text-sm text-slate-600">
        保存済みの月
    </div>
    <div class="divide-y divide-slate-100">
        @forelse ($months as $month)
            <div class="flex items-center justify-between px-4 py-3 text-sm hover:bg-slate-50">
                <a href="{{ route('monthly-affiliations.show', $month) }}" class="font-medium text-blue-600 hover:underline">
                    {{ \App\Models\MonthlyAffiliationRecord::formatYearMonthLabel($month) }}
                </a>
                <div class="flex items-center gap-4 text-sm">
                    <a href="{{ route('monthly-affiliations.show', $month) }}" class="text-slate-500 hover:underline">閲覧</a>
                    <a href="{{ route('monthly-affiliations.export', $month) }}" class="text-slate-500 hover:underline">CSV</a>
                </div>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-slate-400 text-sm">まだ保存された月がありません</p>
        @endforelse
    </div>
</div>
@endsection
