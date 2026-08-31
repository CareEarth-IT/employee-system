@extends('layouts.app')

@section('title', '社員一覧 - CE-Group 社員専用')

@section('mainWidthClass', 'max-w-[96rem]')

@section('content')
@php
    $hasFilters = $keyword !== ''
        || $company !== ''
        || $employeeId !== ''
        || $employmentType !== '';

    $filterQuery = array_filter([
        'status' => $status,
        'company' => $company,
        'employee_id' => $employeeId,
        'employment_type' => $employmentType,
        'keyword' => $keyword,
    ], fn ($value) => $value !== '');

    $searchQuery = array_filter([
        'company' => $company,
        'employee_id' => $employeeId,
        'employment_type' => $employmentType,
        'keyword' => $keyword,
    ], fn ($value) => $value !== '');

    $tabQuery = fn (string $tabStatus) => array_filter(array_merge($searchQuery, [
        'status' => $tabStatus,
        'sort' => $sort === 'employee_id' ? 'employee_id' : null,
        'direction' => $sort === 'employee_id' ? $direction : null,
    ]), fn ($value) => $value !== null && $value !== '');

    $employeeIdSortDirection = ($sort === 'employee_id' && $direction === 'asc') ? 'desc' : 'asc';
    $employeeIdSortUrl = route('employees.index', array_merge($filterQuery, [
        'sort' => 'employee_id',
        'direction' => $employeeIdSortDirection,
    ]));
    $employeeIdSortLabel = match (true) {
        $sort === 'employee_id' && $direction === 'asc' => '昇順',
        $sort === 'employee_id' && $direction === 'desc' => '降順',
        default => '並び替え',
    };
@endphp

<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <h1 class="text-xl font-bold">社員一覧</h1>
    <div class="flex flex-wrap items-center gap-4 text-sm">
        @if ($canExportHrDetails)
            <a href="{{ route('hr-details.export') }}" class="text-blue-600 hover:underline">詳細情報 CSV</a>
        @endif
        @if ($canImportEmployees)
            <a href="{{ route('employees.import.create') }}" class="text-blue-600 hover:underline">社員追加 CSV</a>
        @endif
        @if ($canManageEmployeeRegistry)
            <a href="{{ route('employees.create') }}" class="text-blue-600 hover:underline">新規登録</a>
        @endif
        <a href="{{ route('dashboard') }}" class="text-blue-600 hover:underline">Top Page へ</a>
    </div>
</div>

@if (session('success'))
    <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-green-800 text-sm">
        {{ session('success') }}
    </div>
@endif

<form method="GET" action="{{ route('employees.index') }}" class="bg-white border border-slate-300 rounded-lg p-4 mb-4">
    <input type="hidden" name="status" value="{{ $status }}">
    @if ($sort === 'employee_id')
        <input type="hidden" name="sort" value="employee_id">
        <input type="hidden" name="direction" value="{{ $direction }}">
    @endif
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <label for="company" class="block text-sm mb-1">所属会社</label>
            <select id="company" name="company" class="w-full rounded border border-slate-300 px-3 py-2 text-sm bg-white">
                <option value="">すべて</option>
                @foreach ($companies as $companyName)
                    <option value="{{ $companyName }}" @selected($company === $companyName)>{{ $companyName }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="employee_id" class="block text-sm mb-1">社員ID</label>
            <input
                id="employee_id"
                type="text"
                name="employee_id"
                value="{{ $employeeId }}"
                placeholder="例: 10255"
                class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
        <div>
            <label for="employment_type" class="block text-sm mb-1">雇用形態</label>
            <select id="employment_type" name="employment_type" class="w-full rounded border border-slate-300 px-3 py-2 text-sm bg-white">
                <option value="">すべて</option>
                @foreach ($employmentTypes as $employmentTypeName)
                    <option value="{{ $employmentTypeName }}" @selected($employmentType === $employmentTypeName)>{{ $employmentTypeName }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2 lg:col-span-4">
            <label for="keyword" class="block text-sm mb-1">キーワード検索</label>
            <input
                id="keyword"
                type="text"
                name="keyword"
                value="{{ $keyword }}"
                placeholder="氏名・メール・部署・電話番号など"
                class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
            >
        </div>
    </div>
    <div class="mt-4 flex flex-wrap items-center gap-3">
        <button type="submit" class="rounded bg-blue-600 text-white px-6 py-2 text-sm font-medium hover:bg-blue-700">
            検索
        </button>
        @if ($hasFilters)
            <a href="{{ route('employees.index', ['status' => $status]) }}" class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">
                クリア
            </a>
        @endif
    </div>
</form>

@if ($canManageEmployeeRegistry)
    <p class="mb-3 text-xs text-slate-500">情報システム部・人事部人事課のみ、新規登録ができます。</p>
@endif

<div class="bg-white border border-slate-300 rounded-lg overflow-hidden">
    <nav class="flex border-b border-slate-200 bg-slate-50" aria-label="状況タブ">
        @foreach ($statusTabs as $tabStatus)
            <a
                href="{{ route('employees.index', $tabQuery($tabStatus)) }}"
                @class([
                    'px-4 py-3 text-sm font-medium border-b-2 -mb-px transition-colors',
                    'border-blue-600 text-blue-700 bg-white' => $status === $tabStatus,
                    'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-100' => $status !== $tabStatus,
                ])
            >
                {{ $tabStatus }}
            </a>
        @endforeach
    </nav>
    <div class="px-4 py-3 border-b border-slate-200 bg-slate-50 text-sm text-slate-600">
        {{ $employees->count() }}名
        <span class="text-slate-500">／ 状況: {{ $status }}</span>
        @if ($company !== '')
            <span class="text-slate-500">／ 所属会社: {{ $company }}</span>
        @endif
        @if ($employeeId !== '')
            <span class="text-slate-500">／ 社員ID: {{ $employeeId }}</span>
        @endif
        @if ($employmentType !== '')
            <span class="text-slate-500">／ 雇用形態: {{ $employmentType }}</span>
        @endif
        @if ($keyword !== '')
            <span class="text-slate-500">／ 「{{ $keyword }}」</span>
        @endif
        @if ($sort === 'employee_id')
            <span class="text-slate-500">／ 社員ID: {{ $direction === 'desc' ? '降順' : '昇順' }}</span>
        @endif
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <colgroup>
                <col class="w-[11%]">
                <col class="w-[13%]">
                <col class="w-[26%]">
                <col class="w-[12%]">
                <col class="w-[8%]">
                <col class="w-[12%]">
                <col class="w-[8%]">
                <col class="w-[10%]">
            </colgroup>
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-left text-slate-500">
                    <th class="px-3 py-3">Name (ENG)</th>
                    <th class="px-3 py-3">名前 / カタカナ</th>
                    <th class="px-3 py-3">アドレス</th>
                    <th class="px-3 py-3">電話番号</th>
                    <th class="px-3 py-3 whitespace-nowrap">状況</th>
                    <th class="px-3 py-3">所属会社</th>
                    <th class="px-3 py-3 whitespace-nowrap">
                        <a
                            href="{{ $employeeIdSortUrl }}"
                            class="inline-flex items-center gap-1 text-slate-600 hover:text-blue-600"
                            title="社員IDで{{ $employeeIdSortDirection === 'desc' ? '降順' : '昇順' }}に並べ替え"
                            aria-label="社員IDで並び替え（現在: {{ $employeeIdSortLabel }}）"
                        >
                            社員ID
                            <span class="font-normal text-xs" aria-hidden="true">
                                @if ($sort === 'employee_id' && $direction === 'asc')
                                    ▲
                                @elseif ($sort === 'employee_id' && $direction === 'desc')
                                    ▼
                                @else
                                    ↕
                                @endif
                            </span>
                        </a>
                    </th>
                    <th class="px-3 py-3 whitespace-nowrap">雇用形態</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    @php
                        $profileUrl = auth()->id() === $employee->id
                            ? route('profile.show')
                            : route('users.profile.show', $employee);
                        $englishName = $employee->profile?->english_name;
                    @endphp
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="px-3 py-3 align-top">
                            <a href="{{ $profileUrl }}" class="text-blue-600 hover:underline break-words">
                                {{ $englishName ?: $employee->displayName() }}
                            </a>
                        </td>
                        <td class="px-3 py-3 align-top">
                            <a href="{{ $profileUrl }}" class="text-blue-600 hover:underline break-words">
                                @if ($employee->profile?->name_kana)
                                    {{ $employee->profile->name_kana }}
                                @else
                                    {{ $employee->displayName() }}
                                @endif
                            </a>
                        </td>
                        <td class="px-3 py-3 align-top">
                            @if ($employee->email)
                                <a href="mailto:{{ $employee->email }}" class="text-blue-600 hover:underline break-all">
                                    {{ $employee->email }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-3 align-top">
                            @include('partials.company-phones', ['phones' => $employee->hrDetail?->companyPhoneList() ?? []])
                        </td>
                        <td class="px-3 py-3 align-top whitespace-nowrap">{{ $employee->displayEmploymentStatus() }}</td>
                        <td class="px-3 py-3 align-top">{{ $employee->displayCompany() }}</td>
                        <td class="px-3 py-3 align-top whitespace-nowrap">{{ $employee->employee_id ?? '—' }}</td>
                        <td class="px-3 py-3 align-top whitespace-nowrap">{{ $employee->displayEmploymentType() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-400">
                            @if ($hasFilters)
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
@endsection
