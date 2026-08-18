@extends('layouts.app')

@section('title', 'アクセス方法 - CE-GR 社員専用')

@section('content')
<div class="flex justify-center">
    <div class="w-full max-w-lg bg-white border border-slate-200 rounded-xl shadow-sm p-8 text-center">
        <h1 class="text-xl font-bold text-slate-900">セキュリティーブラウザからアクセスしてください</h1>
        @if (session('error'))
            <div class="mt-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 text-left">
                {{ session('error') }}
            </div>
        @endif
        <p class="mt-4 text-sm text-slate-600 leading-relaxed">
            社員ポータルは、サテライトオフィスのセキュリティーブラウザにサインインしたうえで、
            マイページ等のリンクからお入りください。
        </p>

        @if (! empty($ssoEntryUrl))
            <a
                href="{{ $ssoEntryUrl }}"
                class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700"
            >
                社員ポータルへサインイン（SSO）
            </a>
        @else
            <p class="mt-4 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                SSO 入口 URL が未設定です。管理者に <code>SATERAITO_SSO_ENTRY_URL</code> または <code>SATERAITO_SSO_ID</code> の設定を依頼してください。
            </p>
        @endif

        @if ($showPasswordLogin ?? false)
            <a
                href="{{ route('login') }}"
                class="mt-4 inline-block text-sm text-blue-600 hover:underline"
            >
                メールアドレスとパスワードでログイン
            </a>
        @else
            <ul class="mt-4 text-left text-sm text-slate-600 space-y-2 list-disc list-inside">
                <li>SSO リンク … 自動でログインします</li>
                <li>ログイン画面 … メールアドレスとパスワードでログインします</li>
            </ul>
            <p class="mt-4 text-xs text-slate-500">
                通常のブラウザから直接この URL を開いても、ログインできません。
            </p>
        @endif
    </div>
</div>
@endsection
