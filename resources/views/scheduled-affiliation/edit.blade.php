@extends('layouts.app')

@section('title', '異動・役職の事前登録（編集） - CE-Group 社員専用')

@section('content')
@php
    use App\Support\UserRouteHelper;
@endphp

<div class="max-w-3xl mx-auto">
    <h1 class="mb-2 text-center text-2xl font-bold">異動・役職の事前登録（編集）</h1>
    <p class="mb-6 text-center text-base text-slate-600">{{ $user->displayName() }} さん</p>

    <form method="POST" action="{{ route('scheduled-affiliations.update', $schedule) }}" class="bg-white border border-slate-300 rounded-lg p-8 space-y-6">
        @csrf
        @method('PUT')

        @include('partials.validation-errors-banner')

        @include('scheduled-affiliation._form', [
            'user' => $user,
            'schedule' => $schedule,
            'current' => $current,
            'formValues' => $formValues,
        ])

        <div class="flex flex-row flex-wrap items-center justify-center gap-6 pt-8 mt-2 border-t border-slate-200">
            <a href="{{ UserRouteHelper::route($user, 'profile.edit', 'users.profile.edit') }}" class="whitespace-nowrap rounded border border-slate-300 px-6 py-2.5 text-base hover:bg-slate-50">
                キャンセル
            </a>
            <button type="submit" class="inline-flex min-w-[7.5rem] shrink-0 items-center justify-center whitespace-nowrap rounded bg-blue-600 px-8 py-2.5 text-base font-medium text-white hover:bg-blue-700">
                更新する
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
    @include('partials.skip-stale-form-on-back', ['user' => $user])
@endpush

