@extends('layouts.app')

@section('title', '社員新規登録 - CE-Group 社員専用')

@section('content')
<div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <h1 class="text-xl font-bold">社員新規登録</h1>
    <a href="{{ route('employees.index') }}" class="text-sm text-blue-600 hover:underline">社員一覧へ戻る</a>
</div>

@if ($errors->any())
    <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm">
        入力内容を確認してください。
    </div>
@endif

<form method="POST" action="{{ route('employees.store') }}" class="bg-white border border-slate-300 rounded-lg p-6">
    @csrf
    @include('employees._registry_form', [
        'values' => [
            'name' => old('name'),
            'email' => old('email'),
            'employee_id' => old('employee_id'),
            'department' => old('department'),
            'location' => old('location'),
            'employment_type' => old('employment_type'),
        ],
        'showPasswordRequired' => true,
        'submitLabel' => '登録',
    ])
</form>
@endsection
