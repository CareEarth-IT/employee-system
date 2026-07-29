@php
    $phones = $phones ?? [];
@endphp

@if ($phones !== [])
    <div class="space-y-0.5">
        @foreach ($phones as $phone)
            <div>{{ $phone }}</div>
        @endforeach
    </div>
@else
    —
@endif
