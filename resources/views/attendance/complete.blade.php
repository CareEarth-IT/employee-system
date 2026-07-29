@extends('layouts.app')

@section('title', '連絡完了 - CE-Group 社員専用')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white border border-slate-300 rounded-lg p-8 text-center space-y-6">
        <h1 class="text-xl font-bold">連絡を送信しました</h1>

        <p class="text-slate-700">内容を確認のうえ、必要に応じて上長・人事へ共有されます。</p>

        <dl class="text-left text-sm border-t border-slate-200 pt-6 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2">
            <dt class="text-slate-500">連絡種別</dt>
            <dd>{{ $notification->typeLabel() }}</dd>
            <dt class="text-slate-500">対象日</dt>
            <dd>{{ $notification->dateRangeDisplay() }}</dd>
            @if ($notification->scheduledTimeDisplay())
                <dt class="text-slate-500">{{ $notification->scheduledTimeLabel() }}</dt>
                <dd>{{ $notification->scheduledTimeDisplay() }}</dd>
            @endif
            <dt class="text-slate-500">理由</dt>
            <dd class="whitespace-pre-wrap">{{ $notification->reason }}</dd>
        </dl>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
            <a href="{{ route('attendance-notifications.create') }}" class="rounded bg-blue-600 text-white px-8 py-2 font-medium hover:bg-blue-700">
                続けて連絡する
            </a>
            <a href="{{ route('dashboard') }}" class="rounded border border-slate-300 px-6 py-2 text-sm hover:bg-slate-50">
                Top Page へ
            </a>
        </div>
    </div>
</div>
@endsection
