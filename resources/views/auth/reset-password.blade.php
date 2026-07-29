@extends('layouts.app')

@section('title', '新しいパスワードの設定 - CE-Group 社員専用')

@section('content')
<div class="flex justify-center">
    <div class="w-full max-w-md bg-white border border-slate-300 rounded-lg shadow-sm p-8">
        <h1 class="text-center text-xl font-bold mb-2">新しいパスワードの設定</h1>
        <p class="text-center text-sm text-slate-500 mb-6">新しいパスワードを入力してください。</p>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email" class="block text-sm font-medium mb-1">メールアドレス</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email', $request->email) }}"
                    required
                    @if ($request->email) readonly class="w-full rounded border border-slate-200 bg-slate-50 px-3 py-2 text-slate-600" @else autofocus class="w-full rounded border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" @endif
                >
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium mb-1">新しいパスワード</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    class="w-full rounded border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium mb-1">新しいパスワード（確認）</label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    class="w-full rounded border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>

            <button type="submit" class="w-full rounded bg-blue-600 text-white py-2 font-medium hover:bg-blue-700">
                パスワードを設定
            </button>
        </form>
    </div>
</div>
@endsection
