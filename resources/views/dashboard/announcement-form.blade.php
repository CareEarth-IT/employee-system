@extends('layouts.app')

@section('title', ($content->exists ? 'お知らせ編集' : 'お知らせ作成') . ' - CE-Group 社員専用')

@section('content')
<div class="mx-auto max-w-[1000px]">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">{{ $content->exists ? 'お知らせ編集' : 'お知らせ作成' }}</h1>
        <a href="{{ route('dashboard', ['tab' => $tab]) }}" class="text-sm text-blue-600 hover:underline">Top Page へ戻る</a>
    </div>

    <form method="POST" action="{{ $action }}" class="rounded-lg border border-slate-300 bg-white p-6">
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif

        @include('partials.validation-errors-banner')

        <input type="hidden" name="department" value="{{ $department }}">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="hidden" name="is_visible" value="1">

        <div class="space-y-3">
            <label class="block text-sm font-medium text-slate-700">お知らせ</label>
            <p class="text-xs text-slate-500">部署: {{ $department }}</p>

            <div id="announcement_editor" class="dashboard-quill rounded border border-slate-300 bg-white"></div>
            <textarea
                id="announcement_content_html"
                name="content_html"
                class="hidden"
                required
            >{{ old('content_html', $content->exists ? $content->resolvedHtml() : '') }}</textarea>

            <button type="submit" class="rounded bg-blue-600 px-6 py-2 text-sm font-medium text-white hover:bg-blue-700">
                {{ $submitLabel }}
            </button>
        </div>
    </form>
</div>

@include('dashboard.partials.quill-editor', [
    'editorId' => 'announcement_editor',
    'textareaId' => 'announcement_content_html',
    'department' => $department,
])

@if ($content->exists)
    <form method="POST" action="{{ route('dashboard.announcements.destroy', $content) }}" class="mx-auto mt-4 max-w-[1000px]">
        @csrf
        @method('DELETE')
        <input type="hidden" name="tab" value="{{ $tab }}">
        <button
            type="submit"
            onclick="return confirm('このお知らせを削除しますか？')"
            class="text-sm text-red-700 hover:underline"
        >
            削除
        </button>
    </form>
@endif
@endsection
