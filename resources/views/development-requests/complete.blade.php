@extends(!empty($embed) ? 'layouts.embed' : 'layouts.app')

@section('title', '開発依頼 送信完了 - CE-Group 社員専用')

@section('content')
@php
    $embedQuery = !empty($embed) ? ['embed' => 1] : [];
@endphp
<div class="max-w-xl mx-auto bg-white border border-slate-300 rounded-lg p-8 text-center">
    <h1 class="text-xl font-bold text-green-700 mb-3">送信が完了しました</h1>
    <p class="text-sm text-slate-600 mb-2">依頼 ID: {{ $request->request_number }}</p>
    <p class="text-sm text-slate-600 mb-6">{{ $request->title }}</p>
    <div class="flex flex-wrap justify-center gap-3 text-sm">
        <a href="{{ route('development-requests.create', $embedQuery) }}" class="rounded bg-blue-600 text-white px-5 py-2 hover:bg-blue-700">続けて依頼する</a>
        <a href="{{ route('development-requests.index', $embedQuery) }}" class="rounded border border-slate-300 px-5 py-2 hover:bg-slate-50">一覧を見る</a>
        @if (empty($embed))
            <a href="{{ route('dashboard') }}" class="rounded border border-slate-300 px-5 py-2 hover:bg-slate-50">Top Page へ</a>
        @endif
    </div>
</div>
@endsection
