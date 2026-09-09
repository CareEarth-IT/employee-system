@php
    $tabLinkGroups = $tabLinkGroups ?? [];
    if ($tabLinkGroups === [] && ! empty($tabLinks ?? [])) {
        $tabLinkGroups = [['label' => null, 'links' => $tabLinks]];
    }
@endphp

@if (count($tabLinkGroups) > 0)
    <div @class([
        'space-y-5',
        'mt-8 border-t border-slate-200 pt-8' => $hasAnnouncements ?? false,
    ]) id="dashboard-link-groups">
        @foreach ($tabLinkGroups as $groupIndex => $group)
            @php
                $groupLinks = $group['links'] ?? [];
                $groupLabel = $group['label'] ?? null;
                $groupId = 'dashboard-link-group-' . $groupIndex;
            @endphp

            @if (! empty($groupLabel))
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                    <button
                        type="button"
                        class="dashboard-link-group-toggle flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-base font-bold text-slate-800 transition-colors hover:bg-slate-50"
                        aria-expanded="false"
                        aria-controls="{{ $groupId }}"
                    >
                        <span>{{ $groupLabel }}</span>
                        <span class="dashboard-link-group-chevron text-xs text-slate-400 transition-transform" aria-hidden="true">▼</span>
                    </button>
                    <ul
                        id="{{ $groupId }}"
                        class="dashboard-link-group-panel hidden space-y-3 border-t border-slate-100 px-5 py-4"
                    >
                        @forelse ($groupLinks as $link)
                            <li>
                                @include('dashboard.partials.tab-link-item', ['link' => $link])
                            </li>
                        @empty
                            <li class="text-slate-500">リンクはありません。</li>
                        @endforelse
                    </ul>
                </section>
            @else
                <ul class="space-y-3">
                    @foreach ($groupLinks as $link)
                        <li>
                            @include('dashboard.partials.tab-link-item', ['link' => $link])
                        </li>
                    @endforeach
                </ul>
            @endif
        @endforeach
    </div>

    @once
        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.dashboard-link-group-toggle').forEach((button) => {
                    button.addEventListener('click', () => {
                        const panel = document.getElementById(button.getAttribute('aria-controls') || '');
                        const chevron = button.querySelector('.dashboard-link-group-chevron');
                        const expanded = button.getAttribute('aria-expanded') === 'true';
                        const nextExpanded = !expanded;

                        button.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
                        panel?.classList.toggle('hidden', !nextExpanded);
                        chevron?.classList.toggle('rotate-180', nextExpanded);
                    });
                });
            });
        </script>
        @endpush
    @endonce
@endif
