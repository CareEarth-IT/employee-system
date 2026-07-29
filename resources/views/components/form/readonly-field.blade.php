@props([
    'label' => null,
    'value' => '—',
    'class' => '',
    'breakAll' => false,
])

<div {{ $attributes }}>
    @if ($label)
        <label class="block text-sm mb-1">{!! $label !!}</label>
    @endif
    <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm {{ $class }} @if($breakAll) break-all @endif">{{ $value }}</p>
</div>
