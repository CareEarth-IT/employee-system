@props([
    'field',
    'label',
    'type' => 'text',
    'value' => '',
    'display' => null,
    'editable' => false,
    'minHeight' => null,
])

@php
    $displayText = $display ?? ($value === '' ? '—' : $value);
    if ($display === null && $value === '') {
        $displayText = '—';
    }
@endphp

<div>
    <label class="block text-sm text-slate-500 mb-1">{{ $label }}</label>
    <p
        @if ($editable)
            data-field="{{ $field }}"
            data-type="{{ $type }}"
            data-value="{{ $value }}"
            title="ダブルクリックで編集"
            class="profile-inline-value rounded border border-slate-200 px-3 py-2 cursor-text hover:bg-slate-50 transition-colors {{ $minHeight }}"
        @else
            class="rounded border border-slate-200 px-3 py-2 {{ $minHeight }}"
        @endif
    >{{ $displayText }}</p>
</div>
