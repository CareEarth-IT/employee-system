@extends('layouts.app')

@section('title', 'プロフィール - CE-Group 社員専用')

@section('content')
@php
    use App\Support\EmployeeHrDetailAccess;
    use App\Support\NationalityOptions;
    use App\Support\UserRouteHelper;
    $profile = $user->profile;
    $updateUrl = UserRouteHelper::route($user, 'profile.update', 'users.profile.update');
    $canViewHrDetail = EmployeeHrDetailAccess::canViewPage(auth()->user(), $user);
    $canEditIdentity = auth()->user()->canEditEmployeeIdentity($user);
    $editableProfileFields = $editableProfileFields ?? auth()->user()->editableProfileFieldNames($user);
    $canEditField = fn (string $field): bool => in_array($field, $editableProfileFields, true);
    $canFullyEditProfile = auth()->user()->canFullyEditProfile($user);
    $showPersonalProfileSections = auth()->user()->canViewPersonalProfileSections($user);
@endphp

<div class="mb-4 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-bold">プロフィール</h1>
        <p class="mt-1 text-sm text-slate-500">
            @if ($canFullyEditProfile)
                人事課・総務課・情シス・人事部・役員はプロフィール全体を編集できます。
            @elseif (auth()->id() === $user->id)
                本人はプロフィール項目の編集はできません。閲覧のみです。
            @else
                閲覧のみです。
            @endif
            @if ($canEdit)
                <span class="block mt-1">編集可能な項目はダブルクリックでも編集できます。</span>
            @endif
            @if ($canEditIdentity)
                <span class="block mt-1">社員ID・メールアドレスは情報システム部のみ編集できます。</span>
            @endif
        </p>
    </div>
    <div class="flex gap-3 text-sm">
        <a href="{{ route('dashboard') }}" class="text-blue-600 hover:underline">Top Page へ</a>
        @if ($canEdit)
            <a href="{{ UserRouteHelper::route($user, 'profile.edit', 'users.profile.edit') }}" class="text-blue-600 hover:underline">編集</a>
        @endif
    </div>
</div>

@include('profile._google-workspace-info', [
    'user' => $user,
    'canEditIdentity' => $canEditIdentity,
    'identityUpdateUrl' => $updateUrl,
])

<div class="@class([
    'grid gap-6 mb-8 min-w-0',
    'lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]' => $showPersonalProfileSections,
])">
    <div
        class="min-w-0 overflow-hidden bg-white border border-slate-300 rounded-lg p-6 space-y-4"
        @if ($canEdit)
            data-profile-inline-edit
            data-update-url="{{ $updateUrl }}"
            data-csrf="{{ csrf_token() }}"
        @endif
    >
        <div class="grid sm:grid-cols-2 gap-4">
            @include('profile.partials.inline-field', [
                'field' => 'english_name',
                'label' => 'Eng: Name',
                'value' => $profile?->english_name ?? '',
                'editable' => $canEditField('english_name'),
            ])
            @include('profile.partials.inline-field', [
                'field' => 'name_kana',
                'label' => '名前',
                'value' => $profile?->name_kana ?? '',
                'editable' => $canEditField('name_kana'),
            ])
            @include('profile.partials.inline-field', [
                'field' => 'abbreviated_name',
                'label' => '略氏名',
                'value' => $profile?->abbreviated_name ?? '',
                'editable' => $canEditField('abbreviated_name'),
            ])
            @include('profile.partials.inline-field', [
                'field' => 'joined_at',
                'label' => '入社日',
                'type' => 'date',
                'value' => $profile?->joined_at?->format('Y-m-d') ?? '',
                'display' => $profile?->joined_at?->format('Y/m/d') ?? '—',
                'editable' => $canEditField('joined_at'),
            ])
            @include('profile.partials.inline-field', [
                'field' => 'nationality',
                'label' => '国籍',
                'type' => 'select',
                'options' => NationalityOptions::names(),
                'value' => NationalityOptions::toDisplayName($profile?->nationality) ?? ($profile?->nationality ?? ''),
                'editable' => $canEditField('nationality'),
            ])
            <div>
                <p class="text-sm mb-1">状況</p>
                <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">{{ $user->displayEmploymentStatus() }}</p>
            </div>
            <div>
                <p class="text-sm mb-1">雇用形態</p>
                <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">{{ $user->displayEmploymentType() }}</p>
            </div>
        </div>

    </div>

    @if ($showPersonalProfileSections)
    <div class="min-w-0 bg-white border border-slate-300 rounded-lg p-6 self-start">
        <h2 class="font-bold mb-4">写真登録</h2>
        @if ($profile?->photo_path)
            <img src="{{ $profile->photoUrl() }}" alt="プロフィール写真" class="w-full max-w-[250px] mb-4 rounded border border-slate-200">
        @else
            <div class="w-full max-w-[250px] aspect-square bg-slate-50 border border-dashed border-slate-300 rounded flex items-center justify-center text-slate-500 text-sm mb-4">
                未登録
            </div>
        @endif
        @if ($canEdit)
            <p class="text-xs text-slate-500 mb-2">写真の変更は「編集」画面から行えます。</p>
        @endif
        <p class="text-xs text-slate-500">横幅500px以内・3ヶ月以内の写真</p>
    </div>
    @endif
</div>

@include('profile._affiliation-table', [
    'user' => $user,
    'editable' => auth()->user()->canManageAffiliation($user),
])

@if ($canViewHrDetail)
<div class="mt-6 bg-white border border-slate-300 rounded-lg p-6">
    <h2 class="font-bold mb-2">詳細情報</h2>
    <p class="text-sm text-slate-600 mb-4">状況・雇用形態の編集は詳細情報で行います。閲覧・編集できる範囲は所属により異なります。</p>
    <a
        href="{{ UserRouteHelper::route($user, 'profile.hr-detail.edit', 'users.profile.hr-detail.edit') }}"
        class="inline-block rounded bg-slate-700 text-white px-6 py-2 text-sm font-medium hover:bg-slate-800"
    >
        詳細情報を開く
    </a>
</div>
@endif
@endsection
