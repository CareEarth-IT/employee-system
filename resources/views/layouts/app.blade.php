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
            <div class="max-w-6xl mx-auto flex items-center justify-between px-4">
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

        @auth
            @unless (request()->routeIs('dashboard'))
                <div id="global-back-button-wrap" class="mb-3 hidden">
                    <a
                        href="{{ route('dashboard') }}"
                        id="global-back-button"
                        class="inline-flex items-center gap-1 text-base text-blue-600 hover:underline"
                        title="前の画面に戻ります（登録済みの内容は変更しません）"
                    >← 戻る</a>
                </div>
            @endunless
        @endauth

        @yield('content')
    </main>
    @stack('scripts')
    @auth
        @unless (request()->routeIs('dashboard'))
            <script>
                (() => {
                    const wrap = document.getElementById('global-back-button-wrap');
                    const button = document.getElementById('global-back-button');
                    if (!wrap || !button) {
                        return;
                    }

                    const main = document.querySelector('main');
                    const heading = main?.querySelector('h1');

                    if (heading?.parentElement) {
                        const parent = heading.parentElement;
                        const parentClass = typeof parent.className === 'string' ? parent.className : '';
                        const isTitleRow = /\b(flex|grid)\b/.test(parentClass);
                        const isCenteredColumn = /\bmx-auto\b/.test(parentClass);

                        if (isTitleRow && parent.parentElement) {
                            parent.parentElement.insertBefore(wrap, parent);
                        } else if (isCenteredColumn) {
                            parent.insertBefore(wrap, parent.firstChild);
                        } else {
                            parent.insertBefore(wrap, heading);
                        }
                    }

                    wrap.classList.remove('hidden');

                    button.addEventListener('click', (event) => {
                        // 登録済みデータは触らず、ブラウザ履歴で前画面へ戻るだけ。
                        // 所属の保存/削除後は location.replace でフォーム履歴を消しているので通常の back で足りる。
                        if (window.history.length > 1) {
                            event.preventDefault();
                            sessionStorage.removeItem('employee_skip_stale_form');
                            window.history.back();
                        }
                        // 履歴がない新規タブ等のみ、href（Top Page）へ通常遷移
                    });
                })();
            </script>
        @endunless
    @endauth
</body>
</html>
