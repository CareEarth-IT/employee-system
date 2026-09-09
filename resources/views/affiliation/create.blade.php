@extends('layouts.app')

@section('title', '所属部署登録 - CE-Group 社員専用')

@section('content')
@php
    use App\Support\UserRouteHelper;
@endphp

<div class="max-w-3xl mx-auto">
    <h1 class="mb-6 text-center text-2xl font-bold">所属部署登録</h1>

    <form method="POST" action="{{ UserRouteHelper::route($user, 'affiliations.store', 'users.affiliations.store') }}" class="bg-white border border-slate-300 rounded-lg p-8 space-y-6">
        @csrf

        @include('partials.validation-errors-banner')

        @include('affiliation._form', ['user' => $user])

        <div class="flex flex-row flex-wrap items-center justify-center gap-6 pt-8 mt-2 border-t border-slate-200">
            <a href="{{ UserRouteHelper::route($user, 'profile.edit', 'users.profile.edit') }}" class="whitespace-nowrap rounded border border-slate-300 px-6 py-2.5 text-base hover:bg-slate-50">
                キャンセル
            </a>
            <button type="submit" name="action" value="save" class="inline-flex min-w-[7.5rem] shrink-0 items-center justify-center whitespace-nowrap rounded bg-blue-600 px-8 py-2.5 text-base font-medium text-white hover:bg-blue-700">
                保存
            </button>
            <button type="submit" name="action" value="continue" class="whitespace-nowrap rounded border border-slate-400 px-5 py-2.5 text-base hover:bg-slate-50">
                続けて他の所属部署を登録
            </button>
        </div>
    </form>
</div>
@endsection
