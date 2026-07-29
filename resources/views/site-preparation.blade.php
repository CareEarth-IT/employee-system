@extends('layouts.app')

@section('title', 'サイト準備中 - CE-Group 社員専用')

@push('head')
    <!-- No index -->
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <div class="flex justify-center">
        <div class="w-full max-w-lg bg-white border border-slate-200 rounded-xl shadow-sm p-8 text-center">
            <h1 class="text-2xl font-bold text-slate-900">サイト準備中</h1>
            <p class="mt-3 text-sm text-slate-600">
                CE-Group 社員専用サイトは現在準備中です。
            </p>
            <p class="mt-2 text-sm text-slate-500">
                公開までしばらくお待ちください。
            </p>
        </div>
    </div>
@endsection
