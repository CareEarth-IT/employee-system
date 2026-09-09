@props([
    'name',
    'id' => null,
    'value' => null,
    'required' => false,
    'readonly' => false,
    'inputClass' => 'w-full rounded border border-slate-300 bg-white px-3 py-2 text-base',
])

@php
    use App\Support\DateInput;

    $fieldId = $id ?? $name;
    $resolvedValue = old($name, $value);
    $isoValue = DateInput::toIso($resolvedValue) ?? '';
    $displayValue = DateInput::toDisplay($isoValue) ?? '';
@endphp

@if ($readonly)
    <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-base">
        {{ $displayValue !== '' ? $displayValue : '—' }}
    </p>
@else
    <div class="date-field relative w-full" data-date-field>
        <input
            id="{{ $fieldId }}"
            type="text"
            name="{{ $name }}"
            value="{{ $displayValue !== '' ? $displayValue : $isoValue }}"
            placeholder="YYYY/MM/DD"
            inputmode="numeric"
            autocomplete="off"
            @if ($required) required @endif
            @class([$inputClass, 'pr-10'])
            data-date-text
        >
        <button
            type="button"
            class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 hover:text-slate-600"
            data-date-trigger
            aria-label="カレンダーから選択"
            tabindex="-1"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </button>
        <input
            type="date"
            value="{{ $isoValue }}"
            class="sr-only"
            data-date-picker
            tabindex="-1"
            aria-hidden="true"
        >
    </div>
@endif
