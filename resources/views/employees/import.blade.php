@extends('layouts.app')

@section('title', '社員追加 CSV - CE-Group 社員専用')

@section('content')
<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <h1 class="text-xl font-bold">社員追加 CSV</h1>
    <div class="flex flex-wrap items-center gap-4 text-sm">
        <a href="{{ route('employees.index') }}" class="text-blue-600 hover:underline">社員一覧へ戻る</a>
        <a href="{{ route('dashboard') }}" class="text-blue-600 hover:underline">Top Page へ</a>
    </div>
</div>

@if (session('success'))
    <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-green-800 text-sm">
        {{ session('success') }}
    </div>
@endif

<div class="mb-4 rounded border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 leading-relaxed">
    <p class="font-medium text-slate-800 mb-1">CSV形式</p>
    <ul class="list-disc pl-5 space-y-1">
        <li>人事システムから出力した<strong>20列のCSV</strong>をそのままアップロードできます（余分な列は無視されます）。</li>
        <li>登録に使う列: 社員コード、社員名、社員略名、E-MAIL、所属1部門名、所属1役職名</li>
        <li>初期パスワードは <code class="text-xs bg-slate-200 px-1 rounded">password</code> です。初回ログイン時に変更が必要です。</li>
    </ul>
</div>

<div class="mb-4 rounded border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 leading-relaxed">
    <p class="font-medium text-slate-800 mb-1">注意（既存データは変更しません）</p>
    <ul class="list-disc pl-5 space-y-1">
        <li>CSVに含まれる<strong>未登録のメールアドレスのみ</strong>新規追加します。</li>
        <li>すでに社員一覧にあるメールアドレスは<strong>スキップ</strong>し、上書きしません。</li>
    </ul>
</div>

<div class="bg-white border border-slate-300 rounded-lg p-4 mb-4 space-y-4">
    <div class="text-sm">
        <a href="{{ route('employees.import.template') }}" class="text-blue-600 hover:underline">CSVテンプレートをダウンロード</a>
    </div>

    <form method="POST" action="{{ route('employees.import.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div>
            <label for="csv" class="block text-sm mb-1">CSVファイル <span class="text-red-600">*</span></label>
            <input
                id="csv"
                type="file"
                name="csv"
                accept=".csv,text/csv"
                required
                class="block w-full max-w-xl text-sm"
            >
            @include('partials.field-error', ['field' => 'csv'])
        </div>
        <button type="submit" class="rounded bg-blue-600 text-white px-6 py-2 text-sm font-medium hover:bg-blue-700">
            アップロードして追加
        </button>
    </form>
</div>

@if (session('import_rows'))
    <div class="bg-white border border-slate-300 rounded-lg overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 bg-slate-50 text-sm text-slate-600">処理結果</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr class="text-left text-slate-500">
                        <th class="px-3 py-2">メール</th>
                        <th class="px-3 py-2">名前</th>
                        <th class="px-3 py-2">社員ID</th>
                        <th class="px-3 py-2">部署</th>
                        <th class="px-3 py-2">役職</th>
                        <th class="px-3 py-2">結果</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (session('import_rows') as $row)
                        <tr class="border-b border-slate-100">
                            @foreach ($row as $cell)
                                <td class="px-3 py-2 align-top">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
