@extends('layouts.app')

@section('title', '人事詳細情報 - CE-Group 社員専用')

@section('content')
@php
    use App\Support\EmployeeHrDetailAccess;
    use App\Support\UserRouteHelper;
@endphp

<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-xl font-bold">{{ $access['canSave'] ? '人事詳細情報' : '人事詳細情報（閲覧）' }}</h1>
        <p class="text-sm text-slate-600 mt-1">{{ $user->displayName() }} さん</p>
    </div>
    <div class="flex flex-wrap gap-4 text-sm">
        @if (EmployeeHrDetailAccess::canExportCsvForTarget(auth()->user(), $user))
            <a href="{{ UserRouteHelper::route($user, 'profile.hr-detail.export', 'users.profile.hr-detail.export') }}" class="text-blue-600 hover:underline">CSV出力</a>
        @endif
        <a href="{{ UserRouteHelper::route($user, 'profile.edit', 'users.profile.edit') }}" class="text-blue-600 hover:underline">プロフィール編集へ</a>
        <a href="{{ UserRouteHelper::route($user, 'profile.show', 'users.profile.show') }}" class="text-blue-600 hover:underline">閲覧画面へ</a>
    </div>
</div>

@if ($access['canSave'])
<form
    method="POST"
    action="{{ UserRouteHelper::route($user, 'profile.hr-detail.update', 'users.profile.hr-detail.update') }}"
    class="bg-white border border-slate-300 rounded-lg p-4 sm:p-8 space-y-6 min-w-0"
>
    @csrf
    @method('PUT')
@else
<div class="bg-white border border-slate-300 rounded-lg p-4 sm:p-8 space-y-6 min-w-0">
@endif

    @include('partials.validation-errors-banner')
    @include('profile.hr-detail._form', ['user' => $user, 'hrDetail' => $hrDetail, 'access' => $access])

    @if ($access['canSave'])
        <div class="pt-4 border-t border-slate-200">
            <button type="submit" class="rounded bg-blue-600 text-white px-8 py-2 font-medium hover:bg-blue-700">
                保存
            </button>
        </div>
    @endif
@if ($access['canSave'])
</form>
@else
</div>
@endif
@endsection
