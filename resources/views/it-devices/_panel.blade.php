<div class="space-y-4">
    <p class="text-sm text-slate-600">
        {{ $user->displayName() }}
        @if ($user->employee_id)
            <span class="text-slate-500">（{{ $user->employee_id }}）</span>
        @endif
    </p>

    @if ($access['it']['edit'])
        <form
            method="POST"
            action="{{ route('it-devices.update', $user) }}"
            class="space-y-4"
        >
            @csrf
            @method('PUT')
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="keyword" value="{{ $keyword }}">

            @include('partials.validation-errors-banner')
            @include('profile.hr-detail._it-section', [
                'hrDetail' => $hrDetail,
                'access' => $access,
            ])

            <div class="border-t border-slate-200 pt-4">
                <button type="submit" class="rounded bg-blue-600 px-8 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    保存
                </button>
            </div>
        </form>
    @else
        @php
            $readOnlyAccess = [
                'it' => [
                    'edit' => false,
                    'edit_self_device' => false,
                ],
            ];
        @endphp
        @include('profile.hr-detail._it-section', [
            'hrDetail' => $hrDetail,
            'access' => $readOnlyAccess,
        ])
    @endif
</div>
