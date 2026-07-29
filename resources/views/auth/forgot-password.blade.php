@extends('layouts.app')

@section('title', 'パスワード再設定 - CE-Group 社員専用')

@section('content')
<div class="flex justify-center">
    <div class="w-full max-w-md bg-white border border-slate-300 rounded-lg shadow-sm p-8">
        <h1 class="text-center text-xl font-bold mb-2">パスワード再設定</h1>
        <p class="text-center text-sm text-slate-500 mb-6">
            社員一覧に登録されているメールアドレスを入力してください。<br>
            確認後、パスワード設定画面へ進みます。
        </p>

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium mb-1">メールアドレス</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    class="w-full rounded border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded bg-blue-600 text-white py-2 font-medium hover:bg-blue-700">
                パスワード設定へ進む
            </button>
        </form>

        <a
            href="{{ route('login') }}"
            class="mt-4 block w-full text-center text-sm text-blue-600 hover:underline"
        >
            ログイン画面に戻る
        </a>
    </div>
</div>
@endsection
