@extends('layouts.app')

@section('title', '承認待ち一覧 - CE-Group 社員専用')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">備品購入 承認待ち一覧</h1>
        <a href="{{ route('equipment-purchases.index') }}" class="text-sm text-blue-600 hover:underline">備品購入トップへ</a>
    </div>

    <div class="bg-white border border-slate-300 rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-left text-slate-500">
                    <th class="px-4 py-3">申請日</th>
                    <th class="px-4 py-3">申請者</th>
                    <th class="px-4 py-3">申請タイプ</th>
                    <th class="px-4 py-3">購入商品名</th>
                    <th class="px-4 py-3">価格（税込）</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($applications as $application)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3">{{ $application->application_date->format('Y/m/d') }}</td>
                        <td class="px-4 py-3">{{ $application->user->displayName() }}</td>
                        <td class="px-4 py-3">{{ $application->typeLabel() }}</td>
                        <td class="px-4 py-3">{{ $application->product_name }}</td>
                        <td class="px-4 py-3">{{ number_format($application->price_including_tax) }}円</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('equipment-purchases.approve', $application) }}" class="text-blue-600 hover:underline">承認</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">承認待ちの申請はありません</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
