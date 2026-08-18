@php
    $phones = $phones ?? [];
@endphp

@if ($phones !== [])
    <div class="space-y-0.5">
        @foreach ($phones as $phone)
            <div>{{ \App\Support\CompanyPhone::format($phone) ?? $phone }}</div>
        @endforeach
    </div>
@else
    —
@endif
