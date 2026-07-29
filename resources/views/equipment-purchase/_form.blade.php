@php
    use App\Models\EquipmentPurchaseApplication as EPA;
    use App\Services\EquipmentPurchaseSubmissionPeriod;

    $readonly = $readonly ?? false;
    $application = $application ?? null;
    $user = $user ?? auth()->user();
    $typeLabel = $typeLabel ?? $application?->typeLabel();
    $applicationMonthLabel = $readonly
        ? ($application?->applicationMonthLabel() ?? '—')
        : EquipmentPurchaseSubmissionPeriod::submissionTargetMonthLabel();

    $value = function (string $field, mixed $default = '') use ($readonly, $application) {
        if ($readonly) {
            return $application?->{$field} ?? $default;
        }

        return old($field, $application?->{$field} ?? $default);
    };

    $isPurchasedType = $application?->isPurchasedType()
        ?? EPA::isPurchasedApplicationType($applicationType ?? request('application_type'));
@endphp

<div class="space-y-6">
    <div class="grid sm:grid-cols-2 gap-4">
        <x-form.readonly-field label="申請タイプ" :value="$typeLabel" />
        <x-form.readonly-field label="申請月" :value="$applicationMonthLabel" />
        <x-form.readonly-field label='申請者名 <span class="text-xs text-slate-500">(自動取得)</span>' :value="$user->displayName()" />
        <x-form.readonly-field label='申請者(ID) <span class="text-xs text-slate-500">(自動取得)</span>' :value="$user->employee_id ?? '—'" />
    </div>

    @if ($readonly)
        <x-form.readonly-field :label="$isPurchasedType ? '購入店舗名' : '購入サイト'" :value="$application->purchaseSiteLabel()" class="max-w-md" />
    @elseif ($isPurchasedType)
        <div class="max-w-md">
            <label for="purchase_site" class="block text-sm mb-1">購入店舗名 <span class="text-red-600">*</span></label>
            <input id="purchase_site" name="purchase_site" value="{{ $value('purchase_site') }}" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="例：〇〇ホームセンター 梅田店">
            @include('partials.field-error', ['field' => 'purchase_site'])
        </div>
    @else
        <div class="max-w-md">
            <label for="purchase_site" class="block text-sm mb-1">購入サイト <span class="text-red-600">*</span></label>
            <select id="purchase_site" name="purchase_site" class="w-full rounded border border-slate-300 px-3 py-2">
                <option value="">選択してください</option>
                @foreach (EPA::PURCHASE_SITES as $site)
                    <option value="{{ $site }}" @selected($value('purchase_site') === $site)>{{ $site }}</option>
                @endforeach
            </select>
            @include('partials.field-error', ['field' => 'purchase_site'])
        </div>
    @endif

    <div>
        <label @if (! $readonly) for="purchase_site_url" @endif class="block text-sm mb-1">
            購入サイト URL
            @unless ($readonly)
                @if ($isPurchasedType)
                    <span class="text-xs text-slate-500">(任意入力)</span>
                @else
                    <span class="text-red-600">*</span>
                @endif
            @endunless
        </label>
        @if ($readonly)
            @if ($application->purchase_site_url)
                <p class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm break-all">
                    <a href="{{ $application->purchase_site_url }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">{{ $application->purchase_site_url }}</a>
                </p>
            @else
                <x-form.readonly-field value="—" />
            @endif
        @else
            @unless ($isPurchasedType)
                <p class="mb-2 text-sm text-slate-800">「適格請求書（インボイス）」が発行可能なページのURLを入力してください。</p>
            @endunless
            <input id="purchase_site_url" type="url" name="purchase_site_url" value="{{ $value('purchase_site_url') }}" placeholder="https://" class="w-full rounded border border-slate-300 px-3 py-2">
            @include('partials.field-error', ['field' => 'purchase_site_url'])
        @endif
    </div>

    <div>
        <label @if (! $readonly) for="product_name" @endif class="block text-sm mb-1">購入商品名 @unless ($readonly)<span class="text-red-600">*</span>@endunless</label>
        @if ($readonly)
            <x-form.readonly-field :value="$application->product_name" />
        @else
            <input id="product_name" name="product_name" value="{{ $value('product_name') }}" class="w-full rounded border border-slate-300 px-3 py-2">
            @include('partials.field-error', ['field' => 'product_name'])
        @endif
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label @if (! $readonly) for="size" @endif class="block text-sm mb-1">サイズ <span class="text-xs text-slate-500">(任意入力)</span></label>
            @if ($readonly)
                <x-form.readonly-field :value="$application->size ?: '—'" />
            @else
                <input id="size" name="size" value="{{ $value('size') }}" class="w-full rounded border border-slate-300 px-3 py-2">
            @endif
        </div>
        <div>
            <label @if (! $readonly) for="color_model" @endif class="block text-sm mb-1">カラー/型式 <span class="text-xs text-slate-500">(任意入力)</span></label>
            @if ($readonly)
                <x-form.readonly-field :value="$application->color_model ?: '—'" />
            @else
                <input id="color_model" name="color_model" value="{{ $value('color_model') }}" class="w-full rounded border border-slate-300 px-3 py-2">
            @endif
        </div>
    </div>

    <div class="space-y-4">
        <div class="grid sm:grid-cols-[8rem_minmax(0,1fr)] gap-4 items-start max-w-3xl">
            <div>
                <label @if (! $readonly) for="quantity" @endif class="block text-sm mb-1">数量 @unless ($readonly)<span class="text-red-600">*</span>@endunless</label>
                @if ($readonly)
                    <x-form.readonly-field :value="$application->quantity" />
                @else
                    <input id="quantity" type="number" name="quantity" min="1" value="{{ $value('quantity', 1) }}" class="w-full rounded border border-slate-300 px-3 py-2">
                    @include('partials.field-error', ['field' => 'quantity'])
                @endif
            </div>
            <div class="min-w-0">
                <label @if (! $readonly) for="price_including_tax" @endif class="block text-sm mb-1">価格（税込） @unless ($readonly)<span class="text-red-600">*</span>@endunless</label>
                @if ($readonly)
                    <x-form.readonly-field :value="number_format($application->price_including_tax).'円'" />
                @else
                    <input id="price_including_tax" type="number" name="price_including_tax" min="1" value="{{ $value('price_including_tax') }}" class="w-full max-w-xs rounded border border-slate-300 px-3 py-2">
                    @error('price_including_tax')
                        <p class="text-sm text-red-600 mt-1 whitespace-nowrap">{{ $message }}</p>
                    @enderror
                @endif
            </div>
        </div>
        <div>
            <label @if (! $readonly) for="remarks" @endif class="block text-sm mb-1">備考</label>
            @if ($readonly)
                <x-form.readonly-field :value="$application->remarks ?: '—'" class="min-h-[5rem] whitespace-pre-wrap" />
            @else
                <textarea id="remarks" name="remarks" rows="3" class="w-full rounded border border-slate-300 px-3 py-2">{{ $value('remarks') }}</textarea>
                @include('partials.field-error', ['field' => 'remarks'])
            @endif
        </div>
    </div>

    <div>
        <label @if (! $readonly) for="purchase_reason" @endif class="block text-sm mb-1">購入理由 @unless ($readonly)<span class="text-red-600">*</span>@endunless</label>
        @if ($readonly)
            <x-form.readonly-field :value="$application->purchase_reason" class="min-h-[6rem] whitespace-pre-wrap" />
        @else
            <textarea id="purchase_reason" name="purchase_reason" rows="4" class="w-full rounded border border-slate-300 px-3 py-2">{{ $value('purchase_reason') }}</textarea>
            @include('partials.field-error', ['field' => 'purchase_reason'])
        @endif
    </div>

    <div class="space-y-4">
        @if ($readonly)
            <x-form.readonly-field label="備品の利用先" :value="$application->itemDestinationLabel()" class="max-w-md" />
        @else
            <div>
                <label for="item_destination" class="block text-sm mb-1">備品の利用先 <span class="text-red-600">*</span></label>
                <select id="item_destination" name="item_destination" class="w-full max-w-md rounded border border-slate-300 px-3 py-2" data-conditional-trigger>
                    <option value="">選択してください</option>
                    @foreach (EPA::ITEM_DESTINATIONS as $key => $label)
                        <option value="{{ $key }}" @selected($value('item_destination') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @include('partials.field-error', ['field' => 'item_destination'])
            </div>

            <div id="department-wrap" class="hidden">
                <label for="department" class="block text-sm mb-1">
                    部 <span id="department-required-mark" class="text-red-600">*</span>
                </label>
                <input id="department" name="department" value="{{ $value('department') }}" class="w-full max-w-md rounded border border-slate-300 px-3 py-2">
                @include('partials.field-error', ['field' => 'department'])
            </div>

            <div id="section-wrap" class="hidden">
                <p id="section-only-hint" class="text-xs text-slate-500 mb-2 hidden">部を入力済みの場合、課は任意です。</p>
                <label for="section" class="block text-sm mb-1">
                    課 <span id="section-required-mark" class="text-red-600 hidden">*</span>
                </label>
                <input id="section" name="section" value="{{ $value('section') }}" class="w-full max-w-md rounded border border-slate-300 px-3 py-2">
                @include('partials.field-error', ['field' => 'section'])
            </div>

            <div id="onsite-name-wrap" class="hidden">
                <label for="onsite_name" class="block text-sm mb-1">
                    現場名 <span class="text-red-600">*</span>
                </label>
                <input id="onsite_name" name="onsite_name" value="{{ $value('onsite_name') }}" class="w-full max-w-md rounded border border-slate-300 px-3 py-2" placeholder="例：○○マンション新築工事">
                @include('partials.field-error', ['field' => 'onsite_name'])
            </div>
        @endif
    </div>

    <div class="space-y-4">
        @if ($readonly)
            <x-form.readonly-field label="備品の届先" :value="$application->delivery_destination ? $application->deliveryDestinationLabel() : '—'" class="max-w-md" />
            @if ($application->delivery_destination === EPA::DELIVERY_OTHER)
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-form.readonly-field label="お届け先〒" :value="$application->delivery_zip ?: '—'" />
                    <x-form.readonly-field label="受取人氏名" :value="$application->delivery_recipient_name ?: '—'" />
                </div>
                <x-form.readonly-field label="お届け先" :value="$application->delivery_address ?: '—'" />
                <div class="max-w-md">
                    <x-form.readonly-field label="受取人電話" :value="$application->delivery_recipient_phone ?: '—'" />
                </div>
            @endif
        @else
            <div>
                <label for="delivery_destination" class="block text-sm mb-1">
                    備品の届先
                    @if ($isPurchasedType)
                        <span class="text-xs text-slate-500">(任意入力)</span>
                    @else
                        <span class="text-red-600">*</span>
                    @endif
                </label>
                <select id="delivery_destination" name="delivery_destination" class="w-full max-w-md rounded border border-slate-300 px-3 py-2" data-conditional-trigger>
                    <option value="">選択してください</option>
                    @foreach (EPA::DELIVERY_DESTINATIONS as $key => $label)
                        <option value="{{ $key }}" @selected($value('delivery_destination') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @include('partials.field-error', ['field' => 'delivery_destination'])
            </div>

            <div id="delivery-other-wrap" class="hidden space-y-4">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="delivery_zip" class="block text-sm mb-1">
                            お届け先〒
                            @unless ($isPurchasedType)<span class="text-red-600">*</span>@endunless
                        </label>
                        <input id="delivery_zip" name="delivery_zip" value="{{ $value('delivery_zip') }}" class="w-full rounded border border-slate-300 px-3 py-2">
                        @include('partials.field-error', ['field' => 'delivery_zip'])
                    </div>
                    <div>
                        <label for="delivery_recipient_name" class="block text-sm mb-1">
                            受取人氏名
                            @unless ($isPurchasedType)<span class="text-red-600">*</span>@endunless
                        </label>
                        <input id="delivery_recipient_name" name="delivery_recipient_name" value="{{ $value('delivery_recipient_name') }}" class="w-full rounded border border-slate-300 px-3 py-2">
                        @include('partials.field-error', ['field' => 'delivery_recipient_name'])
                    </div>
                </div>
                <div>
                    <label for="delivery_address" class="block text-sm mb-1">
                        お届け先
                        @unless ($isPurchasedType)<span class="text-red-600">*</span>@endunless
                    </label>
                    <input id="delivery_address" name="delivery_address" value="{{ $value('delivery_address') }}" class="w-full rounded border border-slate-300 px-3 py-2">
                    @include('partials.field-error', ['field' => 'delivery_address'])
                </div>
                <div class="max-w-md">
                    <label for="delivery_recipient_phone" class="block text-sm mb-1">
                        受取人電話
                        @unless ($isPurchasedType)<span class="text-red-600">*</span>@endunless
                    </label>
                    <input id="delivery_recipient_phone" name="delivery_recipient_phone" value="{{ $value('delivery_recipient_phone') }}" class="w-full rounded border border-slate-300 px-3 py-2">
                    @include('partials.field-error', ['field' => 'delivery_recipient_phone'])
                </div>
            </div>
        @endif
    </div>

    <div>
        <p class="block text-sm mb-2">
            購入希望日
            @unless ($readonly)
                @if ($isPurchasedType)
                    <span class="text-xs text-slate-500">(任意入力)</span>
                @else
                    <span class="text-red-600">*</span><span class="text-xs text-slate-600">＊数日かかります</span>
                @endif
            @endunless
        </p>
        @if ($readonly)
            <x-form.readonly-field :value="$application->purchase_urgency ? $application->purchaseUrgencyLabel() : '—'" />
        @else
            <div class="space-y-2">
                @foreach (EPA::PURCHASE_URGENCIES as $key => $label)
                    <label class="flex items-start gap-2 text-sm cursor-pointer">
                        <input
                            type="radio"
                            name="purchase_urgency"
                            value="{{ $key }}"
                            @checked($value('purchase_urgency') === $key)
                            class="mt-1"
                        >
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-slate-600 leading-relaxed">
                ＊選択いただいた購入希望日ですが、最短で3日ほどお時間をいただいております。<br>
                　あらかじめご了承下さい。
            </p>
            @include('partials.field-error', ['field' => 'purchase_urgency'])
        @endif
    </div>
</div>

@if (! $readonly)
    @php
        $conditionalFieldConstants = [
            'destinationDepartmentAll' => EPA::DESTINATION_DEPARTMENT_ALL,
            'destinationSectionOnly' => EPA::DESTINATION_SECTION_ONLY,
            'destinationOnsite' => EPA::DESTINATION_ONSITE,
            'deliveryOther' => EPA::DELIVERY_OTHER,
        ];
    @endphp
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const constants = @json($conditionalFieldConstants);

            const itemDestination = document.getElementById('item_destination');
            const departmentWrap = document.getElementById('department-wrap');
            const sectionWrap = document.getElementById('section-wrap');
            const onsiteNameWrap = document.getElementById('onsite-name-wrap');
            const departmentRequiredMark = document.getElementById('department-required-mark');
            const sectionRequiredMark = document.getElementById('section-required-mark');
            const sectionOnlyHint = document.getElementById('section-only-hint');
            const departmentInput = document.getElementById('department');
            const sectionInput = document.getElementById('section');
            const deliveryDestination = document.getElementById('delivery_destination');
            const deliveryOtherWrap = document.getElementById('delivery-other-wrap');

            if (!itemDestination) return;

            const toggleItemDestination = () => {
                const destination = itemDestination.value;
                const isDepartmentAll = destination === constants.destinationDepartmentAll;
                const isSectionOnly = destination === constants.destinationSectionOnly;
                const isOnsite = destination === constants.destinationOnsite;

                departmentWrap.classList.toggle('hidden', ! isDepartmentAll && ! isSectionOnly);
                sectionWrap.classList.toggle('hidden', ! isSectionOnly);
                sectionOnlyHint?.classList.toggle('hidden', ! isSectionOnly);
                onsiteNameWrap?.classList.toggle('hidden', ! isOnsite);

                if (isDepartmentAll) {
                    departmentRequiredMark.classList.remove('hidden');
                    sectionRequiredMark.classList.add('hidden');
                } else if (isSectionOnly) {
                    toggleSectionRequired();
                } else {
                    departmentRequiredMark.classList.add('hidden');
                    sectionRequiredMark.classList.add('hidden');
                }
            };

            const toggleSectionRequired = () => {
                if (itemDestination.value !== constants.destinationSectionOnly) {
                    return;
                }

                const departmentFilled = departmentInput.value.trim() !== '';
                const sectionFilled = sectionInput.value.trim() !== '';

                sectionRequiredMark.classList.toggle('hidden', departmentFilled);
                departmentRequiredMark.classList.toggle('hidden', departmentFilled || sectionFilled);
            };

            const toggleDelivery = () => {
                deliveryOtherWrap.classList.toggle('hidden', deliveryDestination.value !== constants.deliveryOther);
            };

            itemDestination.addEventListener('change', toggleItemDestination);
            departmentInput?.addEventListener('input', toggleSectionRequired);
            sectionInput?.addEventListener('input', toggleSectionRequired);
            deliveryDestination?.addEventListener('change', toggleDelivery);

            toggleItemDestination();
            toggleSectionRequired();
            if (deliveryDestination) {
                toggleDelivery();
            }
        });
    </script>
    @endpush
@endif
