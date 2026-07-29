@php
    $editable = $editable ?? false;
    $application = $application ?? null;
    $ordererName = $editable
        ? auth()->user()->displayName()
        : ($application->orderer?->displayName() ?? '—');
    $orderDateValue = old(
        'order_date',
        $application->order_date?->format('Y-m-d') ?? '',
    );
    $arrivalDateValue = old(
        'arrival_date',
        $application->arrival_date?->format('Y-m-d') ?? '',
    );
    $receiptIssued = old('receipt_issued', $application->receipt_issued);
@endphp

<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
  <div>
    @if ($editable)
      <label for="order_date" class="block text-sm mb-1">注文日</label>
      <input
        id="order_date"
        type="date"
        name="order_date"
        value="{{ $orderDateValue }}"
        class="w-full rounded border border-slate-300 px-3 py-2"
      >
      @include('partials.field-error', ['field' => 'order_date'])
    @else
      <x-form.readonly-field
        label="注文日"
        :value="$application->orderDateDisplay() ?? '—'"
      />
    @endif
  </div>

  <div>
    @if ($editable)
      <label for="arrival_date" class="block text-sm mb-1">到着日</label>
      <input
        id="arrival_date"
        type="date"
        name="arrival_date"
        value="{{ $arrivalDateValue }}"
        class="w-full rounded border border-slate-300 px-3 py-2"
      >
      @include('partials.field-error', ['field' => 'arrival_date'])
    @else
      <x-form.readonly-field
        label="到着日"
        :value="$application->arrivalDateDisplay() ?? '—'"
      />
    @endif
  </div>

  <div class="flex flex-col justify-end">
    @if ($editable)
      <label class="flex items-center gap-2 text-sm cursor-pointer min-h-[2.625rem]">
        <input
          type="checkbox"
          name="receipt_issued"
          value="1"
          @checked($receiptIssued)
          class="rounded border-slate-300"
        >
        <span>領収書発行した</span>
      </label>
      @include('partials.field-error', ['field' => 'receipt_issued'])
    @else
      <x-form.readonly-field
        label="領収書発行"
        :value="$application->receiptIssuedLabel()"
      />
    @endif
  </div>

  <x-form.readonly-field
    label='発注者名 <span class="text-xs text-slate-500">(自動取得)</span>'
    :value="$ordererName"
  />
</div>
