@extends('layouts.app')

@section('title', 'ログイン - CE-GR 社員専用')

@section('content')
<div class="flex justify-center">
    <div class="w-full max-w-2xl bg-white border border-slate-300 rounded-lg shadow-sm overflow-hidden">
        <div class="flex justify-center items-center px-6 pt-8 pb-4">
            <img
                src="{{ \App\Services\SiteBrandingStorage::url('care-earth-group-webpage.png') }}"
                alt="Care Earth Group Webpage"
                class="block h-14 sm:h-16 w-auto max-w-full object-contain mx-auto"
                width="650"
                height="72"
            >
        </div>

        <div class="px-6 sm:px-8 pt-4 pb-12">
            @if (session('status'))
                <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mx-auto max-w-md space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium mb-1">メール</label>
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

                <div>
                    <label for="password" class="block text-sm font-medium mb-1">パスワード</label>
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

                <button type="submit" class="w-full rounded bg-blue-600 text-white py-2 font-medium hover:bg-blue-700">
                    Login
                </button>
            </form>

            <a
                href="{{ route('password.request') }}"
                class="mx-auto mt-3 mb-2 block w-full max-w-md rounded border border-slate-300 bg-white text-slate-700 py-2 text-center text-sm font-medium hover:bg-slate-50"
            >
                パスワードを再設定して下さい
            </a>
        </div>
    </div>
</div>
@endsection
