@extends('layouts.app')

@section('title', 'Top Page - CE-Group 社員専用')

@section('content')
<div class="mx-auto max-w-[1000px]">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-xl font-bold">Top Page</h1>
        <div class="flex flex-wrap items-center gap-4 text-sm">
            @if ($canEditActiveTab)
                <a href="{{ route('dashboard.announcements.create', ['tab' => $activeTab]) }}" class="text-blue-600 hover:underline">お知らせを作成</a>
                <a href="{{ route('dashboard.links.edit', ['tab' => $activeTab]) }}" class="text-blue-600 hover:underline">リンクを編集</a>
            @endif
            <a href="{{ route('profile.edit') }}" class="text-blue-600 hover:underline">プロフィール編集</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-300 bg-white">
        <nav class="flex flex-wrap border-b border-slate-200 bg-slate-50" aria-label="部署タブ">
            @foreach ($tabs as $tab)
                <a
                    href="{{ route('dashboard', ['tab' => $tab['key']]) }}"
                    class="border-b-2 px-4 py-3 text-sm font-medium transition-colors {{ $activeTab === $tab['key'] ? 'border-blue-600 bg-white text-blue-700' : 'border-transparent text-slate-600 hover:bg-white hover:text-slate-800' }}"
                >
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="min-h-[12rem] px-6 py-6">
            @if (! $canViewActiveTabContent)
                <p class="text-sm text-slate-500">在籍部署が一致しないため、{{ $activeTabLabel }} のお知らせは表示できません。</p>
            @else
                @if ($contents->isNotEmpty())
                    <div class="dashboard-content space-y-6 text-sm leading-relaxed text-slate-800">
                        @foreach ($contents as $content)
                            <div class="dashboard-content-block">
                                <div>{!! $content->resolvedHtml() !!}</div>
                                @if ($canEditActiveTab)
                                    <p class="mt-2">
                                        <a
                                            href="{{ route('dashboard.announcements.edit', ['dashboardContent' => $content, 'tab' => $activeTab]) }}"
                                            class="text-xs text-blue-600 hover:underline"
                                        >
                                            編集
                                        </a>
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-500">{{ $activeTabLabel }} のお知らせはまだ登録されていません。</p>
                @endif
            @endif

            @include('dashboard.partials.tab-links', ['tabLinks' => $tabLinks])
        </div>
    </div>
</div>

@if ($showAttendanceSection && in_array($activeTab, ['dispatch', 'specified-skills', 'telecom'], true))
    <div
        id="attendance-modal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="attendance-modal-title"
    >
        <div id="attendance-backdrop" class="absolute inset-0 bg-slate-900/50"></div>

        <div class="relative w-full max-w-sm rounded-lg border border-slate-300 bg-white p-8 shadow-xl">
            <button
                type="button"
                id="attendance-close"
                class="absolute top-3 right-3 text-xl leading-none text-slate-400 hover:text-slate-600"
                aria-label="閉じる"
            >
                &times;
            </button>

            <h2 id="attendance-modal-title" class="mb-4 text-center text-lg font-bold">出勤管理</h2>
            <ul class="space-y-2 text-sm">
                <li>
                    <a href="https://attendance.moneyforward.com/" target="_blank" rel="noopener" class="text-blue-600 hover:underline">
                        マネーフォワード クラウド勤怠
                    </a>
                </li>
                <li>
                    <a href="{{ route('attendance-notifications.create') }}" class="text-blue-600 hover:underline">欠勤・遅刻早退・有休の連絡</a>
                </li>
            </ul>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const openBtn = document.getElementById('attendance-open');
            const modal = document.getElementById('attendance-modal');
            const closeBtn = document.getElementById('attendance-close');
            const backdrop = document.getElementById('attendance-backdrop');

            if (!openBtn || !modal) return;

            const openModal = () => {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                closeBtn?.focus();
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                openBtn.focus();
            };

            openBtn.addEventListener('click', openModal);
            closeBtn?.addEventListener('click', closeModal);
            backdrop?.addEventListener('click', closeModal);

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        });
    </script>
    @endpush
@endif
@endsection
