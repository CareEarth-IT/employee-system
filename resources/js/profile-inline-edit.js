import { initDateField, normalizeDateInput } from './date-field';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-profile-inline-edit]').forEach((root) => {
        bindInlineFields(root);
    });

    document.querySelectorAll('[data-field][data-update-url]').forEach((element) => {
        if (element.closest('[data-profile-inline-edit]')) {
            return;
        }

        bindInlineField(element);
    });
});

function bindInlineFields(root) {
    root.querySelectorAll('[data-field]').forEach((element) => {
        bindInlineField(element, root);
    });
}

function bindInlineField(element, root = null) {
    if (element.dataset.inlineBound === '1') {
        return;
    }

    element.dataset.inlineBound = '1';
    element.addEventListener('dblclick', () => beginEdit(element, root));
}

function resolveInlineContext(element, root) {
    const scope = root ?? element.closest('[data-profile-inline-edit]');

    return {
        updateUrl: element.dataset.updateUrl ?? scope?.dataset.updateUrl ?? '',
        csrf: element.dataset.csrf ?? scope?.dataset.csrf ?? '',
    };
}

function beginEdit(element, root = null) {
    if (element.dataset.editing === '1' || element.querySelector('input, textarea, select')) {
        return;
    }

    const { updateUrl, csrf } = resolveInlineContext(element, root);

    if (! updateUrl || ! csrf) {
        return;
    }

    const field = element.dataset.field;
    const type = element.dataset.type ?? 'text';
    const rawValue = element.dataset.value ?? '';
    const displayText = element.textContent.trim();
    const isEmptyDisplay = displayText === '—';

    element.dataset.editing = '1';
    element.dataset.originalValue = rawValue;
    element.classList.add('ring-2', 'ring-blue-400', 'bg-white');

    let input;

    if (type === 'textarea') {
        input = document.createElement('textarea');
        input.rows = field === 'self_introduction' ? 6 : 4;
        input.className = 'textarea-contained w-full rounded border border-blue-400 px-3 py-2 outline-none';
        input.value = rawValue;
    } else if (type === 'select') {
        input = document.createElement('select');
        input.className = 'w-full rounded border border-blue-400 px-3 py-2 outline-none bg-white';

        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = '選択してください';
        input.appendChild(emptyOption);

        let options = [];

        try {
            options = JSON.parse(element.dataset.options ?? '[]');
        } catch (error) {
            options = [];
        }

        options.forEach((optionValue) => {
            const option = document.createElement('option');
            option.value = optionValue;
            option.textContent = optionValue;
            option.selected = optionValue === rawValue;
            input.appendChild(option);
        });
    } else if (type === 'date') {
        const wrapper = document.createElement('div');
        wrapper.className = 'date-field relative w-full';
        wrapper.dataset.dateField = '1';

        const textInput = document.createElement('input');
        textInput.type = 'text';
        textInput.placeholder = 'YYYY/MM/DD';
        textInput.value = rawValue;
        textInput.dataset.dateText = '1';
        textInput.className = 'w-full rounded border border-blue-400 px-3 py-2 pr-10 outline-none';

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.dataset.dateTrigger = '1';
        trigger.className = 'absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 hover:text-slate-600';
        trigger.setAttribute('aria-label', 'カレンダーから選択');
        trigger.tabIndex = -1;
        trigger.innerHTML = '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>';

        const picker = document.createElement('input');
        picker.type = 'date';
        picker.value = rawValue;
        picker.dataset.datePicker = '1';
        picker.className = 'sr-only';
        picker.tabIndex = -1;
        picker.setAttribute('aria-hidden', 'true');

        wrapper.append(textInput, trigger, picker);
        initDateField(wrapper);
        input = textInput;
        element.replaceChildren(wrapper);
    } else {
        input = document.createElement('input');
        input.type = type === 'email' ? 'email' : 'text';
        input.className = 'w-full rounded border border-blue-400 px-3 py-2 outline-none';

        if (field === 'abbreviated_name') {
            input.maxLength = 10;
        }

        if (field === 'employee_id') {
            input.maxLength = 5;
            input.inputMode = 'numeric';
            input.pattern = '\\d{5}';
        }

        input.value = rawValue;
    }

    if (type !== 'date') {
        input.dataset.inlineInput = '1';
        element.replaceChildren(input);
    } else {
        input.dataset.inlineInput = '1';
    }

    input.focus();

    if (type !== 'textarea' && input.select) {
        input.select();
    }

    let saving = false;

    const finish = async (save) => {
        if (element.dataset.editing !== '1' || saving) {
            return;
        }

        const nextValue = type === 'date'
            ? normalizeDateInput(input.value.trim())
            : input.value.trim();
        const previousValue = element.dataset.originalValue ?? '';

        if (! save || nextValue === previousValue) {
            restoreDisplay(element, previousValue, isEmptyDisplay && previousValue === '');

            return;
        }

        saving = true;
        input.disabled = true;

        try {
            await persistField(element, field, nextValue, updateUrl, csrf);
        } catch (error) {
            window.alert(error.message ?? '保存に失敗しました。');
            restoreDisplay(element, previousValue, isEmptyDisplay && previousValue === '');
        } finally {
            saving = false;
        }
    };

    input.addEventListener('keydown', (event) => {
        if (type === 'textarea') {
            if (event.key === 'Escape') {
                event.preventDefault();
                finish(false);
            }

            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                event.preventDefault();
                finish(true);
            }

            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            finish(true);
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            finish(false);
        }
    });

    input.addEventListener('blur', () => {
        window.setTimeout(() => {
            if (saving || element.dataset.editing !== '1') {
                return;
            }

            finish(true);
        }, 0);
    });
}

function restoreDisplay(element, rawValue, forceEmptyDisplay = false) {
    const display = forceEmptyDisplay || rawValue === '' ? '—' : formatDisplay(element, rawValue);

    element.dataset.value = rawValue;
    element.dataset.editing = '0';
    element.classList.remove('ring-2', 'ring-blue-400', 'bg-white');
    element.textContent = display;
}

function formatDisplay(element, rawValue) {
    if (element.dataset.type === 'date' && rawValue !== '') {
        const parts = rawValue.split('-');

        if (parts.length === 3) {
            return `${parts[0]}/${String(parts[1]).replace(/^0/, '')}/${String(parts[2]).replace(/^0/, '')}`;
        }
    }

    return rawValue;
}

async function persistField(element, field, value, updateUrl, csrf) {
    const body = new FormData();
    body.append('_token', csrf);
    body.append('_method', 'PUT');
    body.append(field, value);

    const response = await fetch(updateUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body,
    });

    const payload = await response.json().catch(() => ({}));

    if (! response.ok) {
        const message = payload.message
            ?? Object.values(payload.errors ?? {})[0]?.[0]
            ?? '保存に失敗しました。';

        throw new Error(message);
    }

    const fieldPayload = payload.fields?.[field] ?? {};
    const rawValue = fieldPayload.value ?? value;
    const display = fieldPayload.display ?? formatDisplay(element, rawValue);

    element.dataset.value = rawValue;
    element.dataset.editing = '0';
    element.classList.remove('ring-2', 'ring-blue-400', 'bg-white');
    element.textContent = display === '' ? '—' : display;

    if (field === 'employee_id' && rawValue !== '') {
        syncEmployeeUpdateUrl(element, rawValue);
    }
}

function syncEmployeeUpdateUrl(element, employeeId) {
    const updateUrl = element.dataset.updateUrl;

    if (! updateUrl) {
        return;
    }

    element.dataset.updateUrl = updateUrl.replace(
        /(\/users\/)[^/]+(\/profile)/,
        `$1${encodeURIComponent(employeeId)}$2`,
    );
}
