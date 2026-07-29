@extends('layouts.app')

@section('title', '備品購入 - CE-Group 社員専用')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-xl font-bold">備品購入</h1>
        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
            @if (auth()->user()->canViewEquipmentPurchaseList())
                <a href="{{ route('equipment-purchases.list') }}" class="text-blue-600 hover:underline">備品購入 一覧</a>
            @endif
            @if (auth()->user()->canManageEquipmentPurchases())
                <a href="{{ route('equipment-purchases.pending') }}" class="text-blue-600 hover:underline">承認待ち一覧</a>
            @endif
            <a href="{{ route('dashboard') }}" class="text-blue-600 hover:underline">Top-Page</a>
        </div>
    </div>

    <div class="equipment-purchase-index-panel">
        @if (session('error'))
            <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @if (! ($canSubmit ?? true))
            <div class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                {{ \App\Services\EquipmentPurchaseSubmissionPeriod::closedMessage() }}
            </div>
        @elseif (! empty($submissionDeadlineMessage))
            <div class="mb-4 rounded border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                {{ $submissionDeadlineMessage }}
            </div>
        @endif

        <p class="text-center font-medium mb-6">備品購入タイプを選択してください</p>

        <div class="space-y-4 @if (! ($canSubmit ?? true)) opacity-50 pointer-events-none @endif">
            @foreach (\App\Models\EquipmentPurchaseApplication::TYPE_GROUPS as $group)
                <div class="equipment-purchase-type-row">
                    <div class="text-sm font-medium text-slate-700 leading-snug">{{ $group['label'] }}</div>
                    <div class="equipment-purchase-type-actions">
                        @foreach ($group['types'] as $typeKey => $typeLabel)
                            <a
                                href="{{ route('equipment-purchases.create', ['type' => $typeKey]) }}"
                                class="equipment-purchase-type-btn"
                            >
                                {{ $typeLabel }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="equipment-purchase-catalog">
            <p class="mb-3 leading-relaxed">よく買う商品は、下記の一覧から選択してください。</p>
            <a
                href="https://docs.google.com/spreadsheets/d/1wtW-hTe_xJm2DsJ-Q1mqrrJ96KfAP51G5zOJS8SDFVo/edit?usp=sharing"
                target="_blank"
                rel="noopener noreferrer"
                class="equipment-purchase-catalog-link"
            >
                よく買う商品一覧
                <span class="mt-0.5 block text-xs font-normal text-blue-600">Google スプレッドシート</span>
            </a>
        </div>
    </div>
</div>
@endsection
