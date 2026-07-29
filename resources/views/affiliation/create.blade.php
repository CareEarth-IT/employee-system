@extends('layouts.app')

@section('title', '所属部署登録 - CE-Group 社員専用')

@section('content')
@php
    use App\Support\UserRouteHelper;
@endphp

<div class="max-w-3xl mx-auto">
    <h1 class="text-xl font-bold mb-6 text-center">所属部署登録</h1>

    <form method="POST" action="{{ UserRouteHelper::route($user, 'affiliations.store', 'users.affiliations.store') }}" class="bg-white border border-slate-300 rounded-lg p-8 space-y-6">
        @csrf

        @include('partials.validation-errors-banner')

        @include('affiliation._form', ['user' => $user])

        <div class="flex flex-row flex-wrap items-center justify-center gap-6 pt-8 mt-2 border-t border-slate-200">
            <a href="{{ UserRouteHelper::route($user, 'profile.edit', 'users.profile.edit') }}" class="rounded border border-slate-300 px-6 py-2.5 text-sm hover:bg-slate-50 whitespace-nowrap">
                キャンセル
            </a>
            <button type="submit" name="action" value="save" class="inline-flex shrink-0 items-center justify-center min-w-[7.5rem] rounded bg-blue-600 px-8 py-2.5 font-medium text-white hover:bg-blue-700 whitespace-nowrap">
                保存
            </button>
            <button type="submit" name="action" value="continue" class="rounded border border-slate-400 px-5 py-2.5 text-sm hover:bg-slate-50 whitespace-nowrap">
                続けて他の所属部署を登録
            </button>
        </div>
    </form>
</div>
@endsection
