@if ($link->isFormPostKind() && $link->resolvedActionUrl())
    <form method="POST" action="{{ $link->resolvedActionUrl() }}" class="inline">
        @csrf
        <button type="submit" class="text-base text-blue-600 hover:underline">
            {{ $link->label }}
        </button>
    </form>
@elseif ($link->isModalKind() && $link->modal_target)
    <button
        type="button"
        id="{{ $link->modal_target }}"
        class="text-base text-blue-600 hover:underline"
        aria-haspopup="dialog"
        aria-controls="attendance-modal"
    >
        {{ $link->label }}
    </button>
@elseif ($link->url)
    <a href="{{ $link->url }}" class="text-base text-blue-600 hover:underline" target="_blank" rel="noopener noreferrer">{{ $link->label }}</a>
@else
    <span class="text-base text-slate-600">{{ $link->label }}</span>
@endif
