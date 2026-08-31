@extends('layouts.app')

@section('title', '社員編集 - CE-Group 社員専用')

@section('content')
<div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-xl font-bold">社員編集</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $employee->displayName() }}</p>
    </div>
    <div class="flex flex-wrap gap-4 text-sm">
        <a href="{{ route('users.profile.show', $employee) }}" class="text-blue-600 hover:underline">プロフィールを見る</a>
        <a href="{{ route('employees.index') }}" class="text-blue-600 hover:underline">社員一覧へ戻る</a>
    </div>
</div>

@if ($errors->any())
    <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm">
        入力内容を確認してください。
    </div>
@endif

<form method="POST" action="{{ route('employees.update', $employee) }}" class="bg-white border border-slate-300 rounded-lg p-6">
    @csrf
    @method('PUT')
    @include('employees._registry_form', [
        'values' => $formValues,
        'showPasswordRequired' => false,
        'submitLabel' => '更新',
    ])
</form>
@endsection
