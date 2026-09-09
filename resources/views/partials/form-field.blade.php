@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'options' => [],
    'rows' => 3,
    'required' => false,
    'readonly' => false,
    'inputId' => null,
    'readonlyDisplay' => null,
])

@php
    use App\Support\DateInput;

    $fieldId = $inputId ?? $name;
    $resolvedValue = old($name, $value);
    $displayValue = match (true) {
        $readonlyDisplay !== null && $readonlyDisplay !== '' => $readonlyDisplay,
        $type === 'checkbox' => (bool) $resolvedValue ? '✓' : '—',
        $type === 'date' && $value instanceof \Illuminate\Support\Carbon => DateInput::toDisplay($value),
        $type === 'date' && is_string($value) && $value !== '' => DateInput::toDisplay($value) ?? $value,
        $type === 'select' && $options !== [] && ! array_is_list($options) => $options[$resolvedValue] ?? ($resolvedValue ?: '—'),
        default => $resolvedValue ?: '—',
    };
@endphp

<div>
    @unless ($type === 'checkbox')
        <label for="{{ $readonly ? null : $fieldId }}" class="mb-1 block text-base">
            {{ $label }}
            @if ($hint)
                <span class="text-sm text-slate-500">{{ $hint }}</span>
            @endif
        </label>
    @endunless

    @if ($readonly)
        @if ($type === 'checkbox')
            <p class="text-base">
                <span class="text-slate-500">{{ $label }}:</span>
                {{ $displayValue }}
            </p>
        @else
            <p class="break-all whitespace-pre-wrap rounded border border-slate-200 bg-slate-50 px-3 py-2 text-base">{{ $displayValue }}</p>
        @endif
    @elseif ($type === 'textarea')
        <textarea
            id="{{ $fieldId }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            @if ($required) required @endif
            class="textarea-contained w-full rounded border border-slate-300 px-3 py-2 text-base"
        >{{ old($name, $value) }}</textarea>
    @elseif ($type === 'select')
        <select id="{{ $fieldId }}" name="{{ $name }}" @if ($required) required @endif class="w-full rounded border border-slate-300 px-3 py-2 text-base">
            <option value="">選択してください</option>
            @foreach ($options as $optionValue => $optionLabel)
                @php
                    if (is_int($optionValue)) {
                        $optionValue = $optionLabel;
                    }
                @endphp
                <option value="{{ $optionValue }}" @selected((string) $resolvedValue === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @elseif ($type === 'date')
        @include('partials.date-field', [
            'name' => $name,
            'id' => $fieldId,
            'value' => $value,
            'required' => $required,
            'readonly' => false,
        ])
    @elseif ($type === 'checkbox')
        <input type="hidden" name="{{ $name }}" value="0">
        <label class="inline-flex cursor-pointer items-center gap-2 text-base">
            <input
                id="{{ $fieldId }}"
                type="checkbox"
                name="{{ $name }}"
                value="1"
                @checked(old($name, $value))
                class="rounded border-slate-300"
            >
            <span>{{ $label }}</span>
        </label>
    @else
        <input
            id="{{ $fieldId }}"
            type="{{ $type }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            @if ($required) required @endif
            class="w-full rounded border border-slate-300 px-3 py-2 text-base"
        >
    @endif

    @if (! $readonly && $type !== 'checkbox')
        @include('partials.field-error', ['field' => $name])
    @endif
</div>
