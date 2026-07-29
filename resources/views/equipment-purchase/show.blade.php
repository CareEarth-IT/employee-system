@extends('layouts.app')

@section('title', '備品購入 申請詳細 - CE-Group 社員専用')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">備品購入 申請詳細</h1>
        <a href="{{ route('equipment-purchases.list', request()->only(['department', 'location', 'date_from', 'date_to', 'page'])) }}" class="text-sm text-blue-600 hover:underline">一覧へ戻る</a>
    </div>

    <div class="bg-white border border-slate-300 rounded-lg p-8 space-y-6">
        @include('partials.validation-errors-banner', ['class' => 'rounded border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm'])
        @if (session('success'))
            <div class="rounded border border-green-200 bg-green-50 px-4 py-3 text-green-800 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if ($application->isPending() && auth()->user()->canApproveEquipmentPurchase($application))
            <div class="flex justify-end">
                <a href="{{ route('equipment-purchases.approve', $application) }}" class="rounded bg-blue-600 text-white px-6 py-2 text-sm font-medium hover:bg-blue-700">
                    承認画面へ
                </a>
            </div>
        @endif

        @include('equipment-purchase._approval-panel', [
            'application' => $application,
            'readonly' => true,
        ])

        @if ($canEditOrder)
            <form method="POST" action="{{ route('equipment-purchases.order.update', $application) }}" class="space-y-4">
                @csrf
                @method('PUT')
                @include('equipment-purchase._order-panel', [
                    'application' => $application,
                    'editable' => true,
                ])
                <div class="flex justify-end">
                    <button type="submit" class="rounded bg-blue-600 text-white px-6 py-2 text-sm font-medium hover:bg-blue-700">
                        発注情報を保存
                    </button>
                </div>
            </form>
        @else
            @include('equipment-purchase._order-panel', [
                'application' => $application,
                'editable' => false,
            ])
        @endif

        @include('equipment-purchase._form', [
            'application' => $application,
            'user' => $application->user,
            'readonly' => true,
        ])
    </div>
</div>
@endsection
