@extends('layouts.app')

@section('title', '備品購入の申請承認 - CE-Group 社員専用')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">備品購入の申請承認</h1>
        <a href="{{ route('equipment-purchases.pending') }}" class="text-sm text-blue-600 hover:underline">承認待ち一覧へ</a>
    </div>

    @include('partials.validation-errors-banner', ['class' => 'mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm'])

    <form
        method="POST"
        action="{{ route('equipment-purchases.approve.update', $application) }}"
        class="bg-white border border-slate-300 rounded-lg p-8 space-y-6"
        data-submitting-label="処理中..."
    >
        @csrf
        @method('PUT')

        @include('equipment-purchase._approval-panel', [
            'application' => $application,
            'readonly' => false,
        ])

        @include('equipment-purchase._form', [
            'application' => $application,
            'user' => $application->user,
            'readonly' => true,
        ])

        <div class="flex justify-end pt-4 border-t border-slate-200">
            <button type="submit" class="rounded bg-blue-600 text-white px-10 py-2.5 font-medium hover:bg-blue-700">
                承認を確定
            </button>
        </div>
    </form>
</div>
@endsection
