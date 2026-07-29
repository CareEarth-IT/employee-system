@extends('layouts.app')

@section('title', '申請完了 - CE-Group 社員専用')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white border border-slate-300 rounded-lg p-8 text-center space-y-6">
        <h1 class="text-xl font-bold">申請完了</h1>

        <p class="text-slate-700">承認が出るまでお待ちください。</p>

        <div class="text-left text-sm text-slate-600 space-y-3 border-t border-slate-200 pt-6">
            @if ($application->isPurchasedType())
                <p>
                    <span class="font-medium">購入済申請の場合：</span>
                    承認後、楽々精算での経費精算が必要です。
                </p>
            @else
                <p>
                    <span class="font-medium">事前申請の場合：</span>
                    承認後に発注手続きを行います。
                </p>
            @endif

            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 pt-2">
                <dt class="text-slate-500">申請タイプ</dt>
                <dd>{{ $application->typeLabel() }}</dd>
                <dt class="text-slate-500">購入商品名</dt>
                <dd>{{ $application->product_name }}</dd>
                <dt class="text-slate-500">申請月</dt>
                <dd>{{ $application->applicationMonthLabel() }}</dd>
            </dl>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
            <a href="{{ route('equipment-purchases.index') }}" class="rounded bg-blue-600 text-white px-8 py-2 font-medium hover:bg-blue-700">
                備品購入トップへ
            </a>
            <a href="{{ route('dashboard') }}" class="rounded border border-slate-300 px-6 py-2 text-sm hover:bg-slate-50">
                Top Page へ
            </a>
        </div>
    </div>
</div>
@endsection
