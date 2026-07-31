@extends('layouts.app')

@section('title', 'リンク編集 - CE-Group 社員専用')

@section('content')
<div class="mx-auto max-w-[1000px]">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">リンク編集</h1>
        <a href="{{ route('dashboard', ['tab' => $tab]) }}" class="text-sm text-blue-600 hover:underline">Top Page へ戻る</a>
    </div>

    <form method="POST" action="{{ route('dashboard.links.update') }}" class="rounded-lg border border-slate-300 bg-white p-6">
        @csrf
        @method('PUT')

        @include('partials.validation-errors-banner')

        <input type="hidden" name="tab" value="{{ $tab }}">

        <p class="mb-4 text-sm text-slate-600">部署タブ: <span class="font-medium">{{ $tabLabel }}</span></p>
        <p class="mb-4 text-xs text-slate-500">左の ⋮⋮ をドラッグすると並び順を変更できます。保存するとダッシュボードの表示順に反映されます。</p>

        @if ($supportsCategories)
            <div class="mb-8 rounded-lg border border-slate-200 bg-slate-50 p-4">
                <h2 class="mb-2 text-sm font-semibold text-slate-800">カテゴリ設定</h2>
                <p class="mb-4 text-xs text-slate-500">
                    リンクをグループ分けする見出しを設定します。Top Page ではカテゴリ名を押すとリンクが表示されます。
                </p>
                <div class="space-y-4" id="dashboard-categories-editor">
                    @foreach (old('categories', $categories->map(fn ($category) => [
                        'category_key' => $category->category_key,
                        'label' => $category->label,
                        'sort_order' => $category->sort_order,
                    ])->values()->all()) as $index => $category)
                        @include('dashboard.partials.category-row', ['index' => $index, 'category' => $category])
                    @endforeach
                </div>
                <button
                    type="button"
                    id="add-category-row"
                    class="mt-4 rounded border border-slate-300 px-4 py-2 text-sm hover:bg-white"
                >
                    カテゴリを追加
                </button>
            </div>
        @endif

        <h2 class="mb-3 text-sm font-semibold text-slate-800">リンク</h2>
        <div class="space-y-4" id="dashboard-links-editor">
            @foreach (old('links', $links->map(fn ($link) => [
                'label' => $link->label,
                'url' => $link->url,
                'kind' => $link->kind,
                'action_route' => $link->action_route,
                'modal_target' => $link->modal_target,
                'visibility_rule' => $link->visibility_rule,
                'category_key' => $link->category_key,
                'sort_order' => $link->sort_order,
                'is_visible' => $link->is_visible,
            ])->values()->all()) as $index => $link)
                @include('dashboard.partials.link-row', [
                    'index' => $index,
                    'link' => $link,
                    'categoryOptions' => $categoryOptions,
                ])
            @endforeach
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-4">
            <button
                type="button"
                id="add-link-row"
                class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50"
            >
                リンクを追加
            </button>
            <button type="submit" class="rounded bg-blue-600 px-6 py-2 text-sm font-medium text-white hover:bg-blue-700">
                保存
            </button>
        </div>

        <p class="mt-4 text-xs text-slate-500">
            通常のリンクは「表示名」と「URL」を入力してください。URL はサイト内のパス（例: /employees）でも外部サイトでも構いません。
        </p>
    </form>
</div>

<template id="dashboard-link-row-template">
    @include('dashboard.partials.link-row', [
        'index' => '__INDEX__',
        'link' => [
            'label' => '',
            'url' => '',
            'kind' => 'link',
            'action_route' => null,
            'modal_target' => null,
            'visibility_rule' => null,
            'category_key' => null,
            'sort_order' => 0,
            'is_visible' => true,
        ],
        'categoryOptions' => $categoryOptions,
    ])
</template>

@if ($supportsCategories)
<template id="dashboard-category-row-template">
    @include('dashboard.partials.category-row', [
        'index' => '__INDEX__',
        'category' => [
            'category_key' => '',
            'label' => '',
            'sort_order' => 0,
        ],
    ])
</template>
@endif

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const editor = document.getElementById('dashboard-links-editor');
        const addButton = document.getElementById('add-link-row');
        const template = document.getElementById('dashboard-link-row-template');

        if (!editor || !addButton || !template) {
            return;
        }

        let dragRow = null;

        const rows = () => Array.from(editor.querySelectorAll('[data-link-row]'));

        const refreshRowMeta = () => {
            rows().forEach((row, index) => {
                const title = row.querySelector('[data-link-title]');
                if (title) {
                    title.textContent = `リンク ${index + 1}`;
                }

                const sortOrder = row.querySelector('input[name$="[sort_order]"]');
                if (sortOrder) {
                    sortOrder.value = String((index + 1) * 10);
                }
            });
        };

        const nextIndex = () => rows().length;

        const clearDragState = () => {
            rows().forEach((row) => {
                row.setAttribute('draggable', 'false');
                row.classList.remove('opacity-60', 'ring-2', 'ring-blue-200');
            });
            dragRow = null;
        };

        addButton.addEventListener('click', () => {
            const index = nextIndex();
            const html = template.innerHTML.replaceAll('__INDEX__', String(index));
            editor.insertAdjacentHTML('beforeend', html);
            refreshRowMeta();
        });

        editor.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-link-row]');
            if (!button) {
                return;
            }

            const row = button.closest('[data-link-row]');
            if (row) {
                row.remove();
                refreshRowMeta();
            }
        });

        // Only start drag from the handle so inputs remain editable.
        editor.addEventListener('mousedown', (event) => {
            const handle = event.target.closest('[data-drag-handle]');
            const row = event.target.closest('[data-link-row]');
            if (!handle || !row || !editor.contains(row)) {
                return;
            }

            row.setAttribute('draggable', 'true');
        });

        window.addEventListener('mouseup', () => {
            if (!dragRow) {
                rows().forEach((row) => row.setAttribute('draggable', 'false'));
            }
        });

        editor.addEventListener('dragstart', (event) => {
            const row = event.target.closest('[data-link-row]');
            if (!row || row.getAttribute('draggable') !== 'true' || !editor.contains(row)) {
                event.preventDefault();
                return;
            }

            dragRow = row;
            row.classList.add('opacity-60', 'ring-2', 'ring-blue-200');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', 'dashboard-link-row');
        });

        editor.addEventListener('dragend', () => {
            clearDragState();
            refreshRowMeta();
        });

        editor.addEventListener('dragover', (event) => {
            if (!dragRow) {
                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';

            const target = event.target.closest('[data-link-row]');
            if (!target || target === dragRow || !editor.contains(target)) {
                return;
            }

            const rect = target.getBoundingClientRect();
            const before = event.clientY < rect.top + rect.height / 2;
            editor.insertBefore(dragRow, before ? target : target.nextSibling);
        });

        editor.addEventListener('drop', (event) => {
            if (!dragRow) {
                return;
            }

            event.preventDefault();
            refreshRowMeta();
        });

        refreshRowMeta();
    });
</script>
@if ($supportsCategories)
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const editor = document.getElementById('dashboard-categories-editor');
        const addButton = document.getElementById('add-category-row');
        const template = document.getElementById('dashboard-category-row-template');

        if (!editor || !addButton || !template) {
            return;
        }

        let dragRow = null;

        const rows = () => Array.from(editor.querySelectorAll('[data-category-row]'));

        const refreshRowMeta = () => {
            rows().forEach((row, index) => {
                const title = row.querySelector('[data-category-title]');
                if (title) {
                    title.textContent = `カテゴリ ${index + 1}`;
                }

                const sortOrder = row.querySelector('input[name$="[sort_order]"]');
                if (sortOrder) {
                    sortOrder.value = String((index + 1) * 10);
                }
            });
        };

        const nextIndex = () => rows().length;

        const clearDragState = () => {
            rows().forEach((row) => {
                row.setAttribute('draggable', 'false');
                row.classList.remove('opacity-60', 'ring-2', 'ring-blue-200');
            });
            dragRow = null;
        };

        addButton.addEventListener('click', () => {
            const index = nextIndex();
            const html = template.innerHTML.replaceAll('__INDEX__', String(index));
            editor.insertAdjacentHTML('beforeend', html);
            refreshRowMeta();
        });

        editor.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-category-row]');
            if (!button) {
                return;
            }

            const row = button.closest('[data-category-row]');
            if (row) {
                row.remove();
                refreshRowMeta();
            }
        });

        editor.addEventListener('mousedown', (event) => {
            const handle = event.target.closest('[data-category-drag-handle]');
            const row = event.target.closest('[data-category-row]');
            if (!handle || !row || !editor.contains(row)) {
                return;
            }

            row.setAttribute('draggable', 'true');
        });

        window.addEventListener('mouseup', () => {
            if (!dragRow) {
                rows().forEach((row) => row.setAttribute('draggable', 'false'));
            }
        });

        editor.addEventListener('dragstart', (event) => {
            const row = event.target.closest('[data-category-row]');
            if (!row || row.getAttribute('draggable') !== 'true' || !editor.contains(row)) {
                event.preventDefault();
                return;
            }

            dragRow = row;
            row.classList.add('opacity-60', 'ring-2', 'ring-blue-200');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', 'dashboard-category-row');
        });

        editor.addEventListener('dragend', () => {
            clearDragState();
            refreshRowMeta();
        });

        editor.addEventListener('dragover', (event) => {
            if (!dragRow) {
                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';

            const target = event.target.closest('[data-category-row]');
            if (!target || target === dragRow || !editor.contains(target)) {
                return;
            }

            const rect = target.getBoundingClientRect();
            const before = event.clientY < rect.top + rect.height / 2;
            editor.insertBefore(dragRow, before ? target : target.nextSibling);
        });

        editor.addEventListener('drop', (event) => {
            if (!dragRow) {
                return;
            }

            event.preventDefault();
            refreshRowMeta();
        });

        refreshRowMeta();
    });
</script>
@endif
@endpush
@endsection
