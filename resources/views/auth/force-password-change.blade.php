@extends('layouts.app')

@section('title', '初回パスワード変更 - CE-Group 社員専用')

@section('content')
<div class="flex justify-center">
    <div class="w-full max-w-md bg-white border border-slate-300 rounded-lg shadow-sm p-8">
        <h1 class="text-center text-xl font-bold mb-2">初回パスワード変更</h1>
        <p class="text-center text-sm text-slate-500 mb-6">
            セキュリティのため、初期パスワードから新しいパスワードへ変更してください。
        </p>

        <form method="POST" action="{{ route('password.change.update') }}" class="space-y-4">
            @csrf

            <div>
                <label for="password" class="block text-sm font-medium mb-1">新しいパスワード</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autofocus
                    autocomplete="new-password"
                    class="w-full rounded border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-slate-500">8文字以上。初期パスワード（password）と同じ値は使えません。</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium mb-1">新しいパスワード（確認）</label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="w-full rounded border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>

            <button type="submit" class="w-full rounded bg-blue-600 text-white py-2 font-medium hover:bg-blue-700">
                パスワードを変更して続行
            </button>
        </form>
    </div>
</div>
@endsection
