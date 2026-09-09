@extends('layouts.app')

@section('title', '備品購入の申請 - CE-Group 社員専用')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-bold">備品購入の申請</h1>
        <a href="{{ route('equipment-purchases.index') }}" class="text-base text-blue-600 hover:underline">タイプ選択へ戻る</a>
    </div>

    @include('partials.validation-errors-banner', ['class' => 'mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-base'])

    @if (! empty($submissionDeadlineMessage))
        <div class="mb-4 rounded border border-slate-200 bg-slate-50 px-4 py-3 text-base text-slate-700">
            {{ $submissionDeadlineMessage }}
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('equipment-purchases.store') }}"
        class="bg-white border border-slate-300 rounded-lg p-8 space-y-6"
        data-submitting-label="申請中..."
    >
        @csrf
        <input type="hidden" name="application_type" value="{{ $type }}">

        @include('equipment-purchase._form', [
            'user' => $user,
            'typeLabel' => $typeLabel,
            'applicationType' => $type,
            'readonly' => false,
        ])

        <div class="flex justify-end pt-4 border-t border-slate-200">
            <button type="submit" class="rounded bg-blue-600 text-white px-10 py-2.5 text-base font-medium hover:bg-blue-700">
                申請
            </button>
        </div>
    </form>
</div>
@endsection
