<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CE-Group 社員専用')</title>
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
    @auth
        <header class="bg-white border-b border-slate-200">
            <div class="max-w-6xl mx-auto flex items-center justify-between">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center">
                    <img
                        src="{{ \App\Services\SiteBrandingStorage::url('group-web.png') }}"
                        alt="Group Web"
                        class="h-9 w-auto max-w-[12rem] object-contain object-left"
                        width="388"
                        height="97"
                    >
                </a>
                <div class="flex items-center gap-4 text-sm">
                    <span>{{ auth()->user()->displayName() }} さん</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-blue-600 hover:underline">ログアウト</button>
                    </form>
                </div>
            </div>
        </header>
    @endauth

    <main @class([
        'mx-auto px-4 py-8',
        trim($__env->yieldContent('mainWidthClass')) !== '' ? trim($__env->yieldContent('mainWidthClass')) : 'max-w-6xl',
    ])>
        @if (session('success'))
            <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
