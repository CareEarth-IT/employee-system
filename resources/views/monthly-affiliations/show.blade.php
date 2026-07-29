@extends('layouts.app')

@section('title', $yearMonthLabel . ' 所属記録 - CE-Group 社員専用')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-bold">{{ $yearMonthLabel }} の所属記録</h1>
    <a href="{{ route('monthly-affiliations.index') }}" class="text-sm text-blue-600 hover:underline">月次一覧へ</a>
</div>

@if (session('success'))
    <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-green-800 text-sm">
        {{ session('success') }}
    </div>
@endif

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="text-sm text-slate-600">
        保存日時: {{ $capturedAt->timezone(config('app.timezone'))->format('Y/m/d H:i') }}
        — {{ $records->count() }}名
    </div>
    <a
        href="{{ route('monthly-affiliations.export', $yearMonth) }}"
        class="rounded border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50"
    >
        CSVダウンロード
    </a>
</div>

<div class="bg-white border border-slate-300 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-left text-slate-500">
                    <th class="px-4 py-3">名前</th>
                    <th class="px-4 py-3">社員ID</th>
                    <th class="px-4 py-3">拠点</th>
                    <th class="px-4 py-3">部</th>
                    <th class="px-4 py-3">課</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $record)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3">{{ $record->name }}</td>
                        <td class="px-4 py-3">{{ $record->employee_id ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $record->location ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $record->department ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $record->section ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if (session('auto_export'))
    @push('scripts')
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            window.location.href = @json(route('monthly-affiliations.export', $yearMonth));
        });
    </script>
    @endpush
@endif
@endsection
