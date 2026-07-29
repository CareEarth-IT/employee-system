<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CE-Group 社員専用')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    <style>
        html, body { background: #f5f5f5; min-height: 100%; }
        body.embed-body { margin: 0; }
        main.embed-main { max-width: none; width: 100%; padding: 0; }
    </style>
</head>
<body class="embed-body text-slate-800">
    <main class="embed-main">
        @if (session('success') && ! request()->routeIs('development-requests.create'))
            <div class="mb-3 rounded border border-green-200 bg-green-50 px-3 py-2 text-green-800 text-sm" style="margin:12px;">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error') && ! request()->routeIs('development-requests.create'))
            <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-red-800 text-sm" style="margin:12px;">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any() && ! request()->routeIs('development-requests.create'))
            <div class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-red-800 text-sm" style="margin:12px;">
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
