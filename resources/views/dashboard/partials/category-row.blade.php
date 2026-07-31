@php
    $categoryKey = $category['category_key'] ?? '';
    $categoryLabel = $category['label'] ?? '';
@endphp

<div class="rounded border border-slate-200 bg-slate-50 p-4" data-category-row draggable="false">
    <div class="mb-3 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <button
                type="button"
                data-category-drag-handle
                class="cursor-grab touch-none select-none rounded px-1 py-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 active:cursor-grabbing"
                aria-label="ドラッグして並び替え"
                title="ドラッグして並び替え"
            >
                <span class="block leading-none tracking-tighter" aria-hidden="true">⋮⋮</span>
            </button>
            <p class="text-sm font-medium text-slate-700" data-category-title>カテゴリ {{ is_numeric($index) ? ((int) $index + 1) : '' }}</p>
        </div>
        <button type="button" data-remove-category-row class="text-xs text-red-700 hover:underline">削除</button>
    </div>

    <input type="hidden" name="categories[{{ $index }}][sort_order]" value="{{ $category['sort_order'] ?? ((is_numeric($index) ? (int) $index + 1 : 1) * 10) }}">

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm">表示名</label>
            <input
                type="text"
                name="categories[{{ $index }}][label]"
                value="{{ old("categories.$index.label", $categoryLabel) }}"
                class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>
        <div>
            <label class="mb-1 block text-sm">キー（英数字・変更時はリンク再設定）</label>
            <input
                type="text"
                name="categories[{{ $index }}][category_key]"
                value="{{ old("categories.$index.category_key", $categoryKey) }}"
                class="w-full rounded border border-slate-300 px-3 py-2 text-sm font-mono"
                pattern="[a-z0-9_-]+"
                required
            >
        </div>
    </div>
</div>
