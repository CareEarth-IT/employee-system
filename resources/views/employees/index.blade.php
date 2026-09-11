@extends('layouts.app')

@section('title', '社員一覧 - CE-Group 社員専用')

@section('mainWidthClass', 'max-w-[96rem] pb-5')

@section('content')
@php
    use App\Support\EmployeeIndexFilters;

    $hasFilterConditions = count($filterConditions) > 0;
    $hasKeyword = $keyword !== '';
    $hasFilters = $hasKeyword || $hasFilterConditions;
    $filterConditionCount = count($filterConditions);
    $openFilterPanel = $hasFilterConditions;
    $openKeywordPanel = $hasKeyword && ! $hasFilterConditions;
    $searchPanelOpen = $openFilterPanel || $openKeywordPanel;

    $filterQueryParams = EmployeeIndexFilters::toQueryParams($filterConditions);

    $filterQuery = array_filter(array_merge(
        ['status' => $status],
        $filterQueryParams,
        ['keyword' => $keyword],
    ), fn ($value) => $value !== '' && $value !== []);

    $searchQuery = array_filter(array_merge(
        $filterQueryParams,
        ['keyword' => $keyword],
    ), fn ($value) => $value !== '' && $value !== []);

    $tabQuery = fn (string $tabStatus) => array_filter(array_merge($searchQuery, [
        'status' => $tabStatus,
        'sort' => $sort === 'employee_id' ? 'employee_id' : null,
        'direction' => $sort === 'employee_id' ? $direction : null,
    ]), fn ($value) => $value !== null && $value !== '' && $value !== []);

    $employeeIdSortDirection = ($sort === 'employee_id' && $direction === 'asc') ? 'desc' : 'asc';
    $employeeIdSortUrl = route('employees.index', array_merge($filterQuery, [
        'sort' => 'employee_id',
        'direction' => $employeeIdSortDirection,
    ]));
    $employeeIdSortLabel = match (true) {
        $sort === 'employee_id' && $direction === 'asc' => '昇順',
        $sort === 'employee_id' && $direction === 'desc' => '降順',
        default => '並び替え',
    };
@endphp

<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <h1 class="text-2xl font-bold">社員一覧</h1>
    <div class="flex flex-wrap items-center gap-4 text-base">
        @if ($canExportHrDetails)
            <a href="{{ $hrDetailExportUrl }}" class="text-blue-600 hover:underline">詳細情報 CSV</a>
        @endif
        @if ($canImportEmployees)
            <a href="{{ route('employees.import.create') }}" class="text-blue-600 hover:underline">社員追加 CSV</a>
        @endif
        @if ($canManageEmployeeRegistry)
            <a href="{{ route('employees.create') }}" class="text-blue-600 hover:underline">新規登録</a>
        @endif
        <a href="{{ route('dashboard') }}" class="text-blue-600 hover:underline">Top Page へ</a>
    </div>
</div>

@if (session('success'))
    <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-base text-green-800">
        {{ session('success') }}
    </div>
@endif

<div class="bg-white border border-slate-300 rounded-lg overflow-hidden">
    <div class="flex items-center gap-3 border-b border-slate-200 px-4 py-2">
        <div class="flex items-center gap-1">
            <button
                type="button"
                id="toggle-filter-panel"
                @class([
                    'inline-flex items-center gap-1.5 rounded px-2 py-1 text-sm text-slate-700 hover:bg-slate-100',
                    'bg-slate-100' => $openFilterPanel,
                ])
                aria-expanded="{{ $openFilterPanel ? 'true' : 'false' }}"
                aria-controls="filter-panel"
            >
                フィルター
                @if ($filterConditionCount > 0)
                    <span class="inline-flex min-w-[1.25rem] items-center justify-center rounded bg-slate-700 px-1.5 text-xs font-medium text-white">
                        {{ $filterConditionCount }}
                    </span>
                @endif
            </button>
            <button
                type="button"
                id="open-keyword-search"
                @class([
                    'relative rounded p-1.5 text-slate-600 hover:bg-slate-100',
                    'bg-slate-100' => $openKeywordPanel,
                ])
                aria-label="キーワード検索を開く"
                aria-expanded="{{ $openKeywordPanel ? 'true' : 'false' }}"
                aria-controls="keyword-search-panel"
            >
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <circle cx="8.5" cy="8.5" r="5.5"></circle>
                    <path d="M13 13l4 4" stroke-linecap="round"></path>
                </svg>
                @if ($hasKeyword)
                    <span class="absolute right-1 top-1 h-1.5 w-1.5 rounded-full bg-slate-700" aria-hidden="true"></span>
                @endif
            </button>
            @if ($hasFilters)
                <a href="{{ route('employees.index', ['status' => $status]) }}" class="ml-1 text-sm text-slate-600 hover:underline">
                    クリア
                </a>
            @endif
        </div>
    </div>

    <form
        method="GET"
        action="{{ route('employees.index') }}"
        id="employee-search-form"
        autocomplete="off"
        @class([
            'border-b border-slate-200 bg-slate-50',
            'hidden' => ! $searchPanelOpen,
        ])
    >
        <input type="hidden" name="status" value="{{ $status }}">
        @if ($sort === 'employee_id')
            <input type="hidden" name="sort" value="employee_id">
            <input type="hidden" name="direction" value="{{ $direction }}">
        @endif

        <div
            id="keyword-search-panel"
            @class([
                'px-4 py-3 space-y-3',
                'hidden' => ! $openKeywordPanel,
            ])
            aria-labelledby="open-keyword-search"
        >
            <div class="w-full max-w-md">
                <label for="keyword" class="mb-1 block text-sm text-slate-600">キーワード検索</label>
                <input
                    id="keyword"
                    type="text"
                    name="keyword"
                    value="{{ $keyword }}"
                    placeholder="氏名・メール・社員ID・部署など"
                    class="w-full rounded border border-slate-300 px-3 py-1.5 text-sm"
                >
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                    検索
                </button>
            </div>
        </div>

        <div
            id="filter-panel"
            @class([
                'px-4 py-3 space-y-3',
                'hidden' => ! $openFilterPanel,
            ])
            aria-labelledby="toggle-filter-panel"
        >
            <div>
                <div id="filter-rows" class="flex flex-col items-start gap-2.5" data-max-conditions="{{ EmployeeIndexFilters::MAX_CONDITIONS }}"></div>
                <button
                    type="button"
                    id="add-filter-condition"
                    class="mt-2 inline-flex items-center gap-1 pl-10 text-sm text-blue-600 hover:underline"
                >
                    <span aria-hidden="true">+</span>
                    条件を追加
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                    検索
                </button>
            </div>
        </div>
    </form>

    <nav class="flex border-b border-slate-200 bg-slate-50" aria-label="状況タブ">
        @foreach ($statusTabs as $tabStatus)
            <a
                href="{{ route('employees.index', $tabQuery($tabStatus)) }}"
                @class([
                    'px-4 py-3 text-base font-medium border-b-2 -mb-px transition-colors',
                    'border-blue-600 text-blue-700 bg-white' => $status === $tabStatus,
                    'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-100' => $status !== $tabStatus,
                ])
            >
                {{ $tabStatus }}
            </a>
        @endforeach
    </nav>
    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-base text-slate-600">
        {{ $employees->count() }}名
        <span class="text-slate-500">／ 状況: {{ $status }}</span>
        @foreach ($filterConditions as $filter)
            <span class="text-slate-500">
                ／ {{ EmployeeIndexFilters::fieldLabel($filter['field']) }}:
                {{ EmployeeIndexFilters::operatorLabel($filter['op']) }}
                {{ $filter['value'] }}
            </span>
        @endforeach
        @if ($keyword !== '')
            <span class="text-slate-500">／ 「{{ $keyword }}」</span>
        @endif
        @if ($sort === 'employee_id')
            <span class="text-slate-500">／ 社員ID: {{ $direction === 'desc' ? '降順' : '昇順' }}</span>
        @endif
    </div>
    @php
        $employeeListHeaders = [
            'Name (ENG)',
            '名前 / カタカナ',
            'アドレス',
            '電話番号',
            '状況',
            '所属会社',
            '管轄',
            '部署',
            '課',
            '役職',
        ];
        $tableHeadCell = 'px-3 py-3 align-middle text-left font-medium text-slate-600 border-l border-slate-300 first:border-l-0 whitespace-nowrap';
        $tableBodyCell = 'px-3 py-3 align-top border-l border-slate-300 first:border-l-0 whitespace-nowrap';
    @endphp
    <div id="employee-table-panel">
        <div
            id="employee-table-scroll"
            class="overflow-x-auto overflow-y-visible [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
        >
            <table id="employee-table" class="w-max min-w-full border-collapse border border-slate-300 text-base">
            <thead class="bg-slate-50 border-b border-slate-300">
                <tr>
                    <th class="{{ $tableHeadCell }} min-w-[5.5rem]">
                        <a
                            href="{{ $employeeIdSortUrl }}"
                            class="inline-flex items-center gap-1 text-slate-600 hover:text-blue-600"
                            title="社員IDで{{ $employeeIdSortDirection === 'desc' ? '降順' : '昇順' }}に並べ替え"
                            aria-label="社員IDで並び替え（現在: {{ $employeeIdSortLabel }}）"
                        >
                            社員ID
                            <span class="text-sm font-normal" aria-hidden="true">
                                @if ($sort === 'employee_id' && $direction === 'asc')
                                    ▲
                                @elseif ($sort === 'employee_id' && $direction === 'desc')
                                    ▼
                                @else
                                    ↕
                                @endif
                            </span>
                        </a>
                    </th>
                    @foreach ($employeeListHeaders as $header)
                        <th @class([
                            $tableHeadCell,
                            'min-w-[8rem]' => in_array($header, ['Name (ENG)', '名前 / カタカナ', '所属会社'], true),
                            'min-w-[14rem]' => $header === 'アドレス',
                            'min-w-[9rem]' => $header === '電話番号',
                            'min-w-[4.5rem]' => in_array($header, ['状況', '管轄'], true),
                            'min-w-[10rem]' => $header === '部署',
                            'min-w-[18rem]' => $header === '課',
                            'min-w-[6rem]' => $header === '役職',
                        ])>{{ $header }}</th>
                    @endforeach
                    <th class="{{ $tableHeadCell }} min-w-[6rem]">雇用形態</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    @php
                        $profileUrl = auth()->id() === $employee->id
                            ? route('profile.show')
                            : route('users.profile.show', $employee);
                        $englishName = $employee->profile?->english_name;
                    @endphp
                    <tr class="border-b border-slate-200 hover:bg-slate-50">
                        <td class="{{ $tableBodyCell }} min-w-[5.5rem]">{{ $employee->employee_id ?? '—' }}</td>
                        <td class="{{ $tableBodyCell }} min-w-[8rem]">
                            <a href="{{ $profileUrl }}" class="text-blue-600 hover:underline">
                                {{ $englishName ?: $employee->displayName() }}
                            </a>
                        </td>
                        <td class="{{ $tableBodyCell }} min-w-[8rem]">
                            <a href="{{ $profileUrl }}" class="text-blue-600 hover:underline">
                                @if ($employee->profile?->name_kana)
                                    {{ $employee->profile->name_kana }}
                                @else
                                    {{ $employee->displayName() }}
                                @endif
                            </a>
                        </td>
                        <td class="{{ $tableBodyCell }} min-w-[14rem]">
                            @if ($employee->email)
                                <a href="mailto:{{ $employee->email }}" class="text-blue-600 hover:underline">
                                    {{ $employee->email }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="{{ $tableBodyCell }} min-w-[9rem]">
                            @include('partials.company-phones', ['phones' => $employee->hrDetail?->companyPhoneList() ?? []])
                        </td>
                        <td class="{{ $tableBodyCell }} min-w-[4.5rem]">{{ $employee->displayEmploymentStatus() }}</td>
                        <td class="{{ $tableBodyCell }} min-w-[8rem]">{{ $employee->displayCompany() }}</td>
                        <td class="{{ $tableBodyCell }} min-w-[4.5rem]">{{ $employee->displayJurisdiction() }}</td>
                        <td class="{{ $tableBodyCell }} min-w-[10rem]">{{ $employee->displayDepartmentPrimary() }}</td>
                        <td class="{{ $tableBodyCell }} min-w-[18rem]">{{ $employee->displaySectionPrimary() }}</td>
                        <td class="{{ $tableBodyCell }} min-w-[6rem]">{{ $employee->displayPositionPrimary() }}</td>
                        <td class="{{ $tableBodyCell }} min-w-[6rem]">{{ $employee->displayEmploymentType() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="px-4 py-8 text-center text-slate-400">
                            @if ($hasFilters)
                                検索条件に該当する社員はいません
                            @else
                                社員が登録されていません
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>

<div
    id="employee-table-hscroll"
    class="fixed bottom-0 z-30 hidden overflow-x-auto overflow-y-hidden border-t border-slate-300 bg-white/95 py-0.5 shadow-[0_-4px_12px_rgba(15,23,42,0.08)] backdrop-blur-sm"
    aria-label="社員一覧の横スクロール"
>
    <div id="employee-table-hscroll-inner" class="h-px"></div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const filterConfig = @json($filterFieldConfig);
    const fieldConfig = filterConfig.fields ?? filterConfig;
    const fieldGroups = filterConfig.groups ?? [];
    const operatorLabels = filterConfig.operatorLabels ?? {
        contains: '次を含む',
        not_contains: '次を含まない',
        eq: '次と一致',
        not_eq: '次と一致しない',
        empty: '空',
        not_empty: '空でない',
    };
    const presenceOperators = new Set(['empty', 'not_empty']);
    const controlClass = 'rounded border border-slate-300 bg-white text-sm py-1.5';
    const fieldSelectClass = `${controlClass} w-[8.75rem] max-w-[8.75rem] shrink-0 px-2`;
    const operatorSelectClass = `${controlClass} w-[8.5rem] max-w-[8.5rem] shrink-0 px-2`;
    const valueControlClass = `${controlClass} w-full px-2.5`;
    const valueContainerClass = 'w-[11rem] max-w-[11rem] shrink-0';
    const initialConditions = @json($filterConditions);
    const rowsContainer = document.getElementById('filter-rows');
    const addButton = document.getElementById('add-filter-condition');
    const searchForm = document.getElementById('employee-search-form');
    const filterPanel = document.getElementById('filter-panel');
    const keywordPanel = document.getElementById('keyword-search-panel');
    const toggleFilterPanelButton = document.getElementById('toggle-filter-panel');
    const openKeywordSearchButton = document.getElementById('open-keyword-search');
    const keywordInput = document.getElementById('keyword');
    const maxConditions = Number(rowsContainer.dataset.maxConditions || 10);
    const defaultField = fieldGroups[0]?.fields?.[0] ?? Object.keys(fieldConfig)[0];

    const fieldKeysInGroups = (groups) => groups.flatMap((group) => group.fields);

    const getAllRows = () => [...rowsContainer.querySelectorAll('[data-filter-row]')];

    const reindexFilterRows = () => {
        getAllRows().forEach((row, index) => {
            row.dataset.filterIndex = String(index);

            const fieldSelect = row.querySelector('[data-filter-field]');
            const operatorSelect = row.querySelector('[data-filter-operator]');
            const valueControl = row.querySelector('[data-filter-value] input, [data-filter-value] select');

            if (fieldSelect) {
                fieldSelect.name = `filters[${index}][field]`;
            }

            if (operatorSelect) {
                operatorSelect.name = `filters[${index}][op]`;
            }

            if (valueControl) {
                valueControl.name = `filters[${index}][value]`;
            }
        });
    };

    const isFilterPanelOpen = () => ! filterPanel.classList.contains('hidden');
    const isKeywordPanelOpen = () => ! keywordPanel.classList.contains('hidden');

    const updateSearchFormVisibility = () => {
        searchForm.classList.toggle('hidden', ! isFilterPanelOpen() && ! isKeywordPanelOpen());
    };

    const setFilterPanelOpen = (open) => {
        filterPanel.classList.toggle('hidden', ! open);
        toggleFilterPanelButton?.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggleFilterPanelButton?.classList.toggle('bg-slate-100', open);
        updateSearchFormVisibility();
    };

    const setKeywordPanelOpen = (open) => {
        keywordPanel.classList.toggle('hidden', ! open);
        openKeywordSearchButton?.setAttribute('aria-expanded', open ? 'true' : 'false');
        openKeywordSearchButton?.classList.toggle('bg-slate-100', open);
        updateSearchFormVisibility();

        if (open) {
            keywordInput?.focus();
        }
    };

    toggleFilterPanelButton?.addEventListener('click', () => {
        const willOpen = ! isFilterPanelOpen();
        setKeywordPanelOpen(false);
        setFilterPanelOpen(willOpen);
    });

    openKeywordSearchButton?.addEventListener('click', () => {
        const willOpen = ! isKeywordPanelOpen();
        setFilterPanelOpen(false);
        setKeywordPanelOpen(willOpen);
    });

    searchForm?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        const target = event.target;

        if (!(target instanceof HTMLInputElement)) {
            return;
        }

        if (target.type === 'text' || target.type === 'date') {
            event.preventDefault();
            searchForm.requestSubmit();
        }
    });

    const createOption = (value, label, selected = false) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        option.selected = selected;
        return option;
    };

    const populateFieldSelect = (fieldSelect, selectedField, groups) => {
        fieldSelect.innerHTML = '';

        const allowedFields = fieldKeysInGroups(groups);
        const fieldsToRender = selectedField && ! allowedFields.includes(selectedField)
            ? [selectedField, ...allowedFields]
            : allowedFields;

        if (! selectedField) {
            fieldSelect.appendChild(createOption('', '項目を選択', true));
        }

        const appendFieldOption = (fieldKey) => {
            if (! fieldConfig[fieldKey]) {
                return;
            }

            fieldSelect.appendChild(createOption(
                fieldKey,
                fieldConfig[fieldKey].label,
                fieldKey === selectedField,
            ));
        };

        if (groups.length > 0) {
            const rendered = new Set();

            groups.forEach((group) => {
                const groupFields = group.fields.filter((fieldKey) => fieldsToRender.includes(fieldKey));

                if (groupFields.length === 0) {
                    return;
                }

                const optgroup = document.createElement('optgroup');
                optgroup.label = group.label;
                groupFields.forEach((fieldKey) => {
                    if (rendered.has(fieldKey)) {
                        return;
                    }

                    rendered.add(fieldKey);
                    optgroup.appendChild(createOption(
                        fieldKey,
                        fieldConfig[fieldKey].label,
                        fieldKey === selectedField,
                    ));
                });
                fieldSelect.appendChild(optgroup);
            });

            fieldsToRender.forEach((fieldKey) => {
                if (rendered.has(fieldKey)) {
                    return;
                }

                appendFieldOption(fieldKey);
            });

            fieldSelect.value = selectedField || '';

            return;
        }

        fieldsToRender.forEach(appendFieldOption);
        fieldSelect.value = selectedField || '';
    };

    const buildValueControl = (field, value = '') => {
        const config = fieldConfig[field];

        if (config.type === 'select') {
            const select = document.createElement('select');
            select.className = valueControlClass;
            select.autocomplete = 'off';
            select.appendChild(createOption('', '値を選択'));
            config.options.forEach((optionValue) => {
                select.appendChild(createOption(optionValue, optionValue, optionValue === value));
            });
            return select;
        }

        if (config.type === 'date') {
            const input = document.createElement('input');
            input.type = 'date';
            input.value = value;
            input.className = valueControlClass;
            input.autocomplete = 'off';
            return input;
        }

        const input = document.createElement('input');
        input.type = 'text';
        input.value = value;
        input.className = valueControlClass;
        input.autocomplete = 'off';
        input.enterKeyHint = 'done';

        if (config.input === 'employee_id') {
            input.inputMode = 'numeric';
            input.maxLength = 5;
            input.pattern = '\\d{5}';
            input.placeholder = '5桁の数字';
            input.addEventListener('input', () => {
                input.value = input.value.replace(/\D/g, '').slice(0, 5);
            });
        } else if (config.input === 'ascii') {
            input.inputMode = 'email';
            input.placeholder = '半角英数字';
            input.addEventListener('input', () => {
                input.value = input.value.replace(/[^A-Za-z0-9@._+\-]/g, '');
            });
        } else {
            input.placeholder = '値を入力';
        }

        return input;
    };

    const syncValueControl = (row, field, operator, currentValue = '') => {
        const valueContainer = row.querySelector('[data-filter-value]');
        const config = fieldConfig[field];

        if (! config) {
            return;
        }

        if (presenceOperators.has(operator)) {
            valueContainer.className = valueContainerClass;
            valueContainer.innerHTML = '';
            valueContainer.hidden = true;

            return;
        }

        valueContainer.hidden = false;
        valueContainer.className = config.type === 'select'
            ? 'w-[13rem] max-w-[13rem] shrink-0'
            : valueContainerClass;
        valueContainer.innerHTML = '';
        valueContainer.appendChild(buildValueControl(field, currentValue));
        reindexFilterRows();
    };

    const syncRow = (row) => {
        const fieldSelect = row.querySelector('[data-filter-field]');
        const operatorSelect = row.querySelector('[data-filter-operator]');
        const valueContainer = row.querySelector('[data-filter-value]');
        const field = fieldSelect.value;
        const config = fieldConfig[field];

        if (! field || ! config) {
            operatorSelect.innerHTML = '';
            operatorSelect.disabled = true;
            operatorSelect.appendChild(createOption('', '条件', true));

            valueContainer.className = valueContainerClass;
            valueContainer.hidden = false;
            valueContainer.innerHTML = '';
            const valuePlaceholder = document.createElement('select');
            valuePlaceholder.disabled = true;
            valuePlaceholder.className = valueControlClass;
            valuePlaceholder.appendChild(createOption('', '値を選択', true));
            valueContainer.appendChild(valuePlaceholder);

            return;
        }

        operatorSelect.disabled = false;
        const currentValue = valueContainer.querySelector('input, select')?.value || '';
        const previousOperator = row.dataset.operator || operatorSelect.value;

        operatorSelect.innerHTML = '';
        config.operators.forEach((operator, index) => {
            operatorSelect.appendChild(createOption(operator, operatorLabels[operator] ?? operator, index === 0));
        });

        const selectedOperator = previousOperator && config.operators.includes(previousOperator)
            ? previousOperator
            : config.operators[0];
        operatorSelect.value = selectedOperator;
        delete row.dataset.operator;

        syncValueControl(row, field, selectedOperator, currentValue);
    };

    const createRow = (condition = null) => {
        const isFirst = getRowCount() === 0;
        const row = document.createElement('div');
        row.className = 'inline-flex max-w-full items-center gap-2';
        row.dataset.filterRow = 'true';
        if (condition?.op) {
            row.dataset.operator = condition.op;
        }

        const prefix = document.createElement('span');
        prefix.dataset.filterPrefix = 'true';
        prefix.className = 'shrink-0 w-8 text-sm text-slate-500';
        prefix.textContent = isFirst ? '条件' : 'かつ';

        const fieldSelect = document.createElement('select');
        fieldSelect.dataset.filterField = 'true';
        fieldSelect.className = fieldSelectClass;
        fieldSelect.title = '項目';
        fieldSelect.autocomplete = 'off';
        const initialField = condition?.field ?? (isFirst ? defaultField : '');
        populateFieldSelect(fieldSelect, initialField, fieldGroups);

        const operatorSelect = document.createElement('select');
        operatorSelect.dataset.filterOperator = 'true';
        operatorSelect.className = operatorSelectClass;
        operatorSelect.title = '条件';
        operatorSelect.autocomplete = 'off';

        const valueContainer = document.createElement('div');
        valueContainer.dataset.filterValue = 'true';
        valueContainer.className = initialField && fieldConfig[initialField]?.type === 'select'
            ? 'w-[13rem] max-w-[13rem] shrink-0'
            : valueContainerClass;

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'shrink-0 rounded border border-slate-300 px-2 py-1.5 text-sm text-slate-500 hover:bg-slate-50 hover:text-slate-700';
        removeButton.title = '削除';
        removeButton.setAttribute('aria-label', '条件を削除');
        removeButton.textContent = '×';
        removeButton.addEventListener('click', () => {
            const wasFirstRow = row.parentElement === rowsContainer;
            row.remove();

            if (wasFirstRow) {
                promoteFirstNestedRow();
            } else {
                removeNestedContainerIfEmpty();
            }

            refreshRowPrefixes();
            reindexFilterRows();
            updateAddButtonState();
        });

        fieldSelect.addEventListener('change', () => {
            syncRow(row);
            reindexFilterRows();
        });

        operatorSelect.addEventListener('change', () => {
            const currentValue = row.querySelector('[data-filter-value] input, [data-filter-value] select')?.value || '';
            syncValueControl(row, fieldSelect.value, operatorSelect.value, currentValue);
        });

        row.append(prefix, fieldSelect, operatorSelect, valueContainer, removeButton);

        if (isFirst) {
            rowsContainer.appendChild(row);
        } else {
            getNestedContainer().appendChild(row);
        }
        syncRow(row);

        if (condition?.value) {
            const valueControl = valueContainer.querySelector('input, select');
            if (valueControl) {
                valueControl.value = condition.value;
            }
        } else if (! condition) {
            const valueControl = valueContainer.querySelector('input, select');
            if (valueControl) {
                valueControl.value = '';
            }
        }

        reindexFilterRows();
        updateAddButtonState();
    };

    const getRowCount = () => rowsContainer.querySelectorAll('[data-filter-row]').length;

    const getNestedContainer = () => {
        let nested = rowsContainer.querySelector('[data-filter-nested-rows]');

        if (! nested) {
            nested = document.createElement('div');
            nested.dataset.filterNestedRows = 'true';
            nested.className = 'ml-10 flex flex-col gap-2 border-l border-slate-200 pl-3';
            rowsContainer.appendChild(nested);
        }

        return nested;
    };

    const removeNestedContainerIfEmpty = () => {
        const nested = rowsContainer.querySelector('[data-filter-nested-rows]');

        if (nested && nested.children.length === 0) {
            nested.remove();
        }
    };

    const promoteFirstNestedRow = () => {
        const nested = rowsContainer.querySelector('[data-filter-nested-rows]');

        if (! nested || ! nested.firstElementChild) {
            removeNestedContainerIfEmpty();

            return;
        }

        const nextRow = nested.firstElementChild;
        const prefix = nextRow.querySelector('[data-filter-prefix]');

        if (prefix) {
            prefix.textContent = '条件';
        }

        rowsContainer.insertBefore(nextRow, nested);
        removeNestedContainerIfEmpty();
        refreshFieldSelects();
        reindexFilterRows();
    };

    const refreshFieldSelects = () => {
        getAllRows().forEach((row) => {
            const fieldSelect = row.querySelector('[data-filter-field]');
            populateFieldSelect(fieldSelect, fieldSelect.value, fieldGroups);
        });

        reindexFilterRows();
        updateAddButtonState();
    };

    const refreshRowPrefixes = () => {
        const firstRow = rowsContainer.querySelector(':scope > [data-filter-row]');
        const nested = rowsContainer.querySelector('[data-filter-nested-rows]');

        if (firstRow) {
            const prefix = firstRow.querySelector('[data-filter-prefix]');
            if (prefix) {
                prefix.textContent = '条件';
            }
        }

        nested?.querySelectorAll('[data-filter-row]').forEach((row) => {
            const prefix = row.querySelector('[data-filter-prefix]');
            if (prefix) {
                prefix.textContent = 'かつ';
            }
        });
    };

    const updateAddButtonState = () => {
        const atLimit = getRowCount() >= maxConditions;
        addButton.disabled = atLimit;
        addButton.classList.toggle('opacity-50', atLimit);
        addButton.classList.toggle('cursor-not-allowed', atLimit);
    };

    addButton.addEventListener('click', () => {
        if (getRowCount() >= maxConditions) {
            return;
        }
        createRow();
    });

    if (initialConditions.length > 0) {
        initialConditions.forEach((condition) => createRow(condition));
        refreshFieldSelects();
    }

    updateAddButtonState();
})();

(() => {
    const panel = document.getElementById('employee-table-panel');
    const scroll = document.getElementById('employee-table-scroll');
    const bar = document.getElementById('employee-table-hscroll');
    const inner = document.getElementById('employee-table-hscroll-inner');
    const table = document.getElementById('employee-table');

    if (!panel || !scroll || !bar || !inner || !table) {
        return;
    }

    let syncing = false;

    const needsHorizontalScroll = () => scroll.scrollWidth > scroll.clientWidth + 1;

    const syncWidth = () => {
        inner.style.width = `${table.scrollWidth}px`;
    };

    const syncBarPosition = () => {
        const rect = panel.getBoundingClientRect();
        const visible = rect.top < window.innerHeight && rect.bottom > 0;

        if (!visible || !needsHorizontalScroll()) {
            bar.classList.add('hidden');
            return;
        }

        bar.classList.remove('hidden');
        bar.style.left = `${rect.left}px`;
        bar.style.width = `${rect.width}px`;
    };

    const syncScrollFromTable = () => {
        if (syncing) {
            return;
        }

        syncing = true;
        bar.scrollLeft = scroll.scrollLeft;
        syncing = false;
    };

    const syncScrollFromBar = () => {
        if (syncing) {
            return;
        }

        syncing = true;
        scroll.scrollLeft = bar.scrollLeft;
        syncing = false;
    };

    const refresh = () => {
        syncWidth();
        syncBarPosition();
        syncScrollFromTable();
    };

    refresh();

    scroll.addEventListener('scroll', syncScrollFromTable, { passive: true });
    bar.addEventListener('scroll', syncScrollFromBar, { passive: true });
    window.addEventListener('resize', refresh, { passive: true });
    window.addEventListener('scroll', syncBarPosition, { passive: true });

    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(refresh);
        observer.observe(table);
        observer.observe(panel);
        observer.observe(scroll);
    }
})();
</script>
@endpush
