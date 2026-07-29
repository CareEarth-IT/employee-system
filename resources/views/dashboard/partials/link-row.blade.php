@php
    $kind = $link['kind'] ?? 'link';
    $isSpecialKind = in_array($kind, ['form_post', 'modal'], true);
@endphp

<div class="rounded border border-slate-200 bg-white p-4" data-link-row draggable="false">
    <div class="mb-3 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <button
                type="button"
                data-drag-handle
                class="cursor-grab touch-none select-none rounded px-1 py-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 active:cursor-grabbing"
                aria-label="ドラッグして並び替え"
                title="ドラッグして並び替え"
            >
                <span class="block leading-none tracking-tighter" aria-hidden="true">⋮⋮</span>
            </button>
            <p class="text-sm font-medium text-slate-700" data-link-title>リンク {{ is_numeric($index) ? ((int) $index + 1) : '' }}</p>
        </div>
        <button type="button" data-remove-link-row class="text-xs text-red-700 hover:underline">削除</button>
    </div>

    <input type="hidden" name="links[{{ $index }}][kind]" value="{{ $kind }}">
    <input type="hidden" name="links[{{ $index }}][action_route]" value="{{ $link['action_route'] ?? '' }}">
    <input type="hidden" name="links[{{ $index }}][modal_target]" value="{{ $link['modal_target'] ?? '' }}">
    <input type="hidden" name="links[{{ $index }}][visibility_rule]" value="{{ $link['visibility_rule'] ?? '' }}">
    <input type="hidden" name="links[{{ $index }}][sort_order]" value="{{ $link['sort_order'] ?? ((is_numeric($index) ? (int) $index + 1 : 1) * 10) }}">

    <div class="grid gap-4 sm:grid-cols-[1fr_1.5fr_auto] sm:items-end">
        <div>
            <label class="mb-1 block text-sm">表示名</label>
            <input
                type="text"
                name="links[{{ $index }}][label]"
                value="{{ old("links.$index.label", $link['label'] ?? '') }}"
                class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                required
            >
        </div>
        <div>
            <label class="mb-1 block text-sm">URL</label>
            @if ($isSpecialKind)
                <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                    {{ $kind === 'form_post' ? 'フォーム送信リンク（URLなし）' : 'モーダル表示リンク（URLなし）' }}
                </p>
            @else
                <input
                    type="text"
                    name="links[{{ $index }}][url]"
                    value="{{ old("links.$index.url", $link['url'] ?? '') }}"
                    class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                    placeholder="/employees または https://..."
                >
            @endif
        </div>
        <label class="flex items-center gap-2 pb-2 text-sm">
            <input type="hidden" name="links[{{ $index }}][is_visible]" value="0">
            <input
                type="checkbox"
                name="links[{{ $index }}][is_visible]"
                value="1"
                @checked(old("links.$index.is_visible", $link['is_visible'] ?? true))
                class="rounded"
            >
            表示
        </label>
    </div>
</div>
