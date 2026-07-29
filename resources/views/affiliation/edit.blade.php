@extends('layouts.app')

@section('title', '所属部署編集 - CE-Group 社員専用')

@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-xl font-bold mb-6 text-center">所属部署編集</h1>

    <div class="bg-white border border-slate-300 rounded-lg p-8">
        <form id="affiliation-update-form" method="POST" action="{{ route('affiliations.update', $affiliation) }}" class="space-y-6">
            @csrf
            @method('PUT')

            @include('partials.validation-errors-banner')

            @include('affiliation._form', ['affiliation' => $affiliation, 'user' => $user])
        </form>

        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 mt-6 border-t border-slate-200">
            <form method="POST" action="{{ route('affiliations.destroy', $affiliation) }}" onsubmit="return confirm('削除しますか？')" class="order-3 sm:order-1">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm text-red-600 hover:underline">Delete</button>
            </form>
            <button type="submit" form="affiliation-update-form" name="action" value="save" class="inline-flex shrink-0 items-center justify-center min-w-[7.5rem] rounded bg-blue-600 px-8 py-2.5 font-medium text-white hover:bg-blue-700 whitespace-nowrap">
                保存
            </button>
            <span class="order-2 sm:order-3 hidden sm:block w-16"></span>
        </div>
    </div>
</div>
@endsection
