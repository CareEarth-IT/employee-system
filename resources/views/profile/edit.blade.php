@extends('layouts.app')

@section('title', 'プロフィール編集 - CE-Group 社員専用')

@section('content')
@php
    use App\Support\UserRouteHelper;
    $profile = $user->profile;
    $canEditIdentity = auth()->user()->canEditEmployeeIdentity($user);
    $updateUrl = UserRouteHelper::route($user, 'profile.update', 'users.profile.update');
@endphp

<div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-bold">プロフィール編集</h1>
    <a href="{{ UserRouteHelper::route($user, 'profile.show', 'users.profile.show') }}" class="text-sm text-blue-600 hover:underline">閲覧画面へ</a>
</div>

@include('profile._google-workspace-info', [
    'user' => $user,
    'canEditIdentity' => $canEditIdentity,
    'identityUpdateUrl' => $updateUrl,
])

<form method="POST" action="{{ UserRouteHelper::route($user, 'profile.update', 'users.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @method('PUT')

    <div class="grid lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] gap-6 min-w-0">
        <div class="lg:col-span-1 bg-white border border-slate-300 rounded-lg p-6 space-y-4 min-w-0 overflow-hidden">
            @include('partials.validation-errors-banner')

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="english_name" class="block text-sm mb-1">Eng: Name <span class="text-xs text-slate-500">(パスポート表記)</span></label>
                    <input id="english_name" name="english_name" value="{{ old('english_name', $profile?->english_name) }}" class="w-full rounded border border-slate-300 px-3 py-2">
                    @include('partials.field-error', ['field' => 'english_name'])
                </div>
                <div>
                    <label for="name_kana" class="block text-sm mb-1">名前</label>
                    <input id="name_kana" name="name_kana" value="{{ old('name_kana', $profile?->name_kana) }}" class="w-full rounded border border-slate-300 px-3 py-2">
                    @include('partials.field-error', ['field' => 'name_kana'])
                </div>
                <div>
                    <label for="abbreviated_name" class="block text-sm mb-1">略氏名 <span class="text-xs text-slate-500">(10文字以内)</span></label>
                    <input id="abbreviated_name" name="abbreviated_name" maxlength="10" value="{{ old('abbreviated_name', $profile?->abbreviated_name) }}" class="w-full rounded border border-slate-300 px-3 py-2">
                    @include('partials.field-error', ['field' => 'abbreviated_name'])
                </div>
                <div>
                    <label for="joined_at" class="block text-sm mb-1">入社日</label>
                    <input id="joined_at" type="date" name="joined_at" value="{{ old('joined_at', $profile?->joined_at?->format('Y-m-d')) }}" class="w-full rounded border border-slate-300 px-3 py-2">
                    @include('partials.field-error', ['field' => 'joined_at'])
                </div>
                <div>
                    <label for="nationality" class="block text-sm mb-1">国籍</label>
                    <input id="nationality" name="nationality" value="{{ old('nationality', $profile?->nationality) }}" class="w-full rounded border border-slate-300 px-3 py-2">
                    @include('partials.field-error', ['field' => 'nationality'])
                </div>
            </div>

            <div class="min-w-0 overflow-hidden">
                <label for="languages" class="block text-sm mb-1">話せる言語</label>
                <textarea id="languages" name="languages" rows="4" wrap="soft" class="textarea-contained rounded border border-slate-300 px-3 py-2">{{ old('languages', $profile?->languages) }}</textarea>
                @include('partials.field-error', ['field' => 'languages'])
            </div>

            <div class="min-w-0 overflow-hidden">
                <label for="self_introduction" class="block text-sm mb-1">自己紹介文</label>
                <textarea id="self_introduction" name="self_introduction" rows="6" wrap="soft" class="textarea-contained rounded border border-slate-300 px-3 py-2">{{ old('self_introduction', $profile?->self_introduction) }}</textarea>
                @include('partials.field-error', ['field' => 'self_introduction'])
            </div>

            <button type="submit" class="rounded bg-blue-600 text-white px-8 py-2 font-medium hover:bg-blue-700">
                SAVE
            </button>
        </div>

        <div class="min-w-0 bg-white border border-slate-300 rounded-lg p-6 self-start">
            <h2 class="font-bold mb-4">写真登録</h2>
            @if ($profile?->photo_path)
                <img src="{{ $profile->photoUrl() }}" alt="プロフィール写真" class="w-full max-w-[250px] mb-4 rounded border border-slate-200">
            @endif
            <input type="file" name="photo" accept="image/*" class="w-full text-sm">
            @include('partials.field-error', ['field' => 'photo'])
            <p class="mt-2 text-xs text-slate-500">横幅500px以内・3ヶ月以内の写真</p>
        </div>
    </div>
</form>

@include('profile._affiliation-table', ['user' => $user, 'editable' => true])

@php
    use App\Support\EmployeeHrDetailAccess;
@endphp
@if (EmployeeHrDetailAccess::canViewPage(auth()->user(), $user))
<div class="mt-6 bg-white border border-slate-300 rounded-lg p-6">
    <h2 class="font-bold mb-2">詳細情報</h2>
    <p class="text-sm text-slate-600 mb-4">入社・退職手続き、在留資格、社保、ITデバイスなどの詳細項目を登録・編集します。閲覧・編集できる範囲は所属により異なります。</p>
    <a
        href="{{ UserRouteHelper::route($user, 'profile.hr-detail.edit', 'users.profile.hr-detail.edit') }}"
        class="inline-block rounded bg-slate-700 text-white px-6 py-2 text-sm font-medium hover:bg-slate-800"
    >
        詳細情報の登録・編集
    </a>
</div>
@endif

@if ($canDeleteEmployee ?? false)
<div class="mt-6 bg-white border border-red-200 rounded-lg p-6">
    <h2 class="font-bold text-red-700 mb-2">社員情報の削除</h2>
    <p class="text-sm text-slate-600 mb-4">
        {{ $user->displayName() }} さんの社員情報を削除します。プロフィール・所属履歴など関連データもすべて削除され、元に戻せません。
    </p>
    <form
        method="POST"
        action="{{ route('users.profile.destroy', $user) }}"
        onsubmit="return confirm(@json($user->displayName().' さんの社員情報を削除します。よろしいですか？'))"
    >
        @csrf
        @method('DELETE')
        <button
            type="submit"
            class="inline-block rounded border border-red-300 bg-red-50 px-6 py-2 text-sm font-medium text-red-800 hover:bg-red-100"
        >
            社員情報を削除
        </button>
    </form>
</div>
@endif
@endsection
