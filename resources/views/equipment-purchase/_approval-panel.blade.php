@php
    use App\Models\EquipmentPurchaseApplication;
    $readonly = $readonly ?? true;
    $application = $application ?? null;
    $isFirstStage = ! $readonly && $application?->isAwaitingFirstApproval();
    $isSecondStage = ! $readonly && $application?->isAwaitingSecondApproval();
    $viewer = auth()->user();
    $isGlobalApprover = ! $readonly && $viewer?->isGlobalEquipmentApprover();
    $defaultConfirmerName = $isGlobalApprover
        ? old('approver_display_name', $viewer->equipmentApprovalConfirmName())
        : $viewer?->displayName();
    $storedFinalConfirmer = $application?->approver_display_name
        ?: $application?->approver?->displayName();
    $storedFirstConfirmer = $application?->first_approver_display_name
        ?: $application?->firstApprover?->displayName();
@endphp

<div class="rounded-lg border-2 border-blue-400 bg-blue-50/30 p-4 space-y-4">
    <h2 class="font-bold text-sm">承認</h2>

    @if ($isFirstStage)
        <p class="text-sm text-blue-800 bg-blue-100 border border-blue-200 rounded px-3 py-2">
            東京の対象部門（営業部・SS課・管理部・GR部）の申請です。1次承認（部長）を行ってください。承認後、支店長へ承認依頼が送られます。
        </p>
    @elseif ($isSecondStage)
        <p class="text-sm text-blue-800 bg-blue-100 border border-blue-200 rounded px-3 py-2">
            1次承認（部長）が完了しています。2次承認（支店長）を行ってください。
        </p>
    @endif

    @if ($application?->hasFirstApproval())
        <div class="rounded border border-slate-200 bg-white p-3 space-y-2">
            <h3 class="text-sm font-semibold text-slate-700">1次承認（部長）</h3>
            <div class="grid sm:grid-cols-2 gap-3 text-sm">
                <div>
                    <p class="text-slate-500 text-xs mb-1">日時</p>
                    <p>{{ $application->firstApprovedAtDisplay() ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-slate-500 text-xs mb-1">確認者</p>
                    <p>{{ $storedFirstConfirmer ?: '—' }}</p>
                </div>
            </div>
            <div>
                <p class="text-slate-500 text-xs mb-1">判定</p>
                <p class="font-medium">{{ $application->firstApprovalDecisionLabel() ?: '—' }}</p>
            </div>
            @if ($application->first_approval_memo)
                <div>
                    <p class="text-slate-500 text-xs mb-1">メモ</p>
                    <p class="whitespace-pre-wrap">{{ $application->first_approval_memo }}</p>
                </div>
            @endif
        </div>
    @endif

    @if ($isSecondStage || ($readonly && $application?->status !== EquipmentPurchaseApplication::STATUS_PENDING))
        <div class="space-y-4 {{ $application?->hasFirstApproval() ? 'pt-2 border-t border-slate-200' : '' }}">
            @if ($application?->hasFirstApproval())
                <h3 class="text-sm font-semibold text-slate-700">2次承認（支店長）</h3>
            @endif

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    @if ($readonly)
                        <label class="block text-sm mb-1">Datetime <span class="text-xs text-slate-500">(日本時間)</span></label>
                        <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                            {{ $application->approvedAtDisplay() ?? '—' }}
                        </p>
                    @else
                        <label for="approved_at" class="block text-sm mb-1">Datetime <span class="text-xs text-slate-500">(日本時間)</span></label>
                        <input
                            id="approved_at"
                            type="datetime-local"
                            name="approved_at"
                            value="{{ old('approved_at', ($application->approved_at ?? now(config('app.timezone')))->timezone(config('app.timezone'))->format('Y-m-d\TH:i')) }}"
                            class="w-full rounded border border-slate-300 px-3 py-2 bg-white"
                        >
                        @include('partials.field-error', ['field' => 'approved_at'])
                    @endif
                </div>
                <div>
                    <label class="block text-sm mb-1">確認者 @unless ($readonly)<span class="text-xs text-slate-500">{{ $isGlobalApprover ? '(入力)' : '(自動取得)' }}</span>@endunless</label>
                    @if ($readonly)
                        <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                            {{ $storedFinalConfirmer ?: '—' }}
                        </p>
                    @elseif ($isGlobalApprover)
                        <input
                            id="approver_display_name"
                            type="text"
                            name="approver_display_name"
                            value="{{ $defaultConfirmerName }}"
                            required
                            class="w-full rounded border border-slate-300 px-3 py-2 bg-white"
                            placeholder="確認者名を入力"
                        >
                        @include('partials.field-error', ['field' => 'approver_display_name'])
                    @else
                        <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                            {{ $defaultConfirmerName }}
                        </p>
                    @endif
                </div>
            </div>

            <div>
                @if ($readonly)
                    <p class="block text-sm mb-2">承認</p>
                    <p class="text-sm font-medium {{ $application->approvalStatusColorClass() }}">
                        {{ $application->approvalSummaryText() }}
                    </p>
                @else
                    <p class="block text-sm mb-2">承認 <span class="text-red-600">*</span></p>
                    <div class="flex flex-wrap gap-6">
                        @foreach (EquipmentPurchaseApplication::APPROVAL_DECISIONS as $key => $label)
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input
                                    type="radio"
                                    name="approval_decision"
                                    value="{{ $key }}"
                                    @checked(old('approval_decision', $application->approval_decision) === $key)
                                    class="rounded-full"
                                >
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @include('partials.field-error', ['field' => 'approval_decision'])
                @endif
            </div>

            <div>
                @if ($readonly)
                    <label class="block text-sm mb-1">メモ</label>
                    <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm min-h-[4rem] whitespace-pre-wrap">{{ $application->approval_memo ?: '—' }}</p>
                @else
                    <label for="approval_memo" class="block text-sm mb-1">メモ</label>
                    <textarea id="approval_memo" name="approval_memo" rows="3" class="w-full rounded border border-slate-300 px-3 py-2 bg-white">{{ old('approval_memo', $application->approval_memo) }}</textarea>
                    @include('partials.field-error', ['field' => 'approval_memo'])
                @endif
            </div>
        </div>
    @elseif (! $readonly || ! $application?->hasFirstApproval())
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                @if ($readonly)
                    <label class="block text-sm mb-1">Datetime <span class="text-xs text-slate-500">(日本時間)</span></label>
                    <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        {{ $application->approvedAtDisplay() ?? '—' }}
                    </p>
                @else
                    <label for="approved_at" class="block text-sm mb-1">Datetime <span class="text-xs text-slate-500">(日本時間)</span></label>
                    <input
                        id="approved_at"
                        type="datetime-local"
                        name="approved_at"
                        value="{{ old('approved_at', ($application->approved_at ?? now(config('app.timezone')))->timezone(config('app.timezone'))->format('Y-m-d\TH:i')) }}"
                        class="w-full rounded border border-slate-300 px-3 py-2 bg-white"
                    >
                    @include('partials.field-error', ['field' => 'approved_at'])
                @endif
            </div>
            <div>
                <label class="block text-sm mb-1">確認者 @unless ($readonly)<span class="text-xs text-slate-500">{{ $isGlobalApprover ? '(入力)' : '(自動取得)' }}</span>@endunless</label>
                @if ($readonly)
                    <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        {{ $storedFinalConfirmer ?: '—' }}
                    </p>
                @elseif ($isGlobalApprover)
                    <input
                        id="approver_display_name"
                        type="text"
                        name="approver_display_name"
                        value="{{ $defaultConfirmerName }}"
                        required
                        class="w-full rounded border border-slate-300 px-3 py-2 bg-white"
                        placeholder="確認者名を入力"
                    >
                    @include('partials.field-error', ['field' => 'approver_display_name'])
                @else
                    <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                        {{ $defaultConfirmerName }}
                    </p>
                @endif
            </div>
        </div>

        <div>
            @if ($readonly)
                <p class="block text-sm mb-2">承認</p>
                <p class="text-sm font-medium {{ $application->approvalStatusColorClass() }}">
                    {{ $application->approvalSummaryText() }}
                </p>
            @else
                <p class="block text-sm mb-2">承認 <span class="text-red-600">*</span></p>
                <div class="flex flex-wrap gap-6">
                    @foreach (EquipmentPurchaseApplication::APPROVAL_DECISIONS as $key => $label)
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input
                                type="radio"
                                name="approval_decision"
                                value="{{ $key }}"
                                @checked(old('approval_decision', $application->approval_decision) === $key)
                                class="rounded-full"
                            >
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @include('partials.field-error', ['field' => 'approval_decision'])
            @endif
        </div>

        <div>
            @if ($readonly)
                <label class="block text-sm mb-1">メモ</label>
                <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm min-h-[4rem] whitespace-pre-wrap">{{ $application->approval_memo ?: '—' }}</p>
            @else
                <label for="approval_memo" class="block text-sm mb-1">メモ</label>
                <textarea id="approval_memo" name="approval_memo" rows="3" class="w-full rounded border border-slate-300 px-3 py-2 bg-white">{{ old('approval_memo', $application->approval_memo) }}</textarea>
                @include('partials.field-error', ['field' => 'approval_memo'])
            @endif
        </div>
    @elseif ($readonly)
        <p class="text-sm font-medium {{ $application->approvalStatusColorClass() }}">
            {{ $application->approvalSummaryText() }}
        </p>
    @endif
</div>
