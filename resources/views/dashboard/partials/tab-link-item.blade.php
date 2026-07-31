@if ($link->isFormPostKind() && $link->resolvedActionUrl())
    <form method="POST" action="{{ $link->resolvedActionUrl() }}" class="inline">
        @csrf
        <button type="submit" class="text-blue-600 hover:underline">
            {{ $link->label }}
        </button>
    </form>
@elseif ($link->isModalKind() && $link->modal_target)
    <button
        type="button"
        id="{{ $link->modal_target }}"
        class="text-blue-600 hover:underline"
        aria-haspopup="dialog"
        aria-controls="attendance-modal"
    >
        {{ $link->label }}
    </button>
@elseif ($link->url)
    <a href="{{ $link->url }}" class="text-blue-600 hover:underline">{{ $link->label }}</a>
@else
    <span class="text-slate-600">{{ $link->label }}</span>
@endif
