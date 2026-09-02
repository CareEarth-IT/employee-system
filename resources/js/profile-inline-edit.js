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
        input = document.createElement('input');
        input.type = 'date';
        input.className = 'w-full rounded border border-blue-400 px-3 py-2 outline-none';
        input.value = rawValue;
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

    input.dataset.inlineInput = '1';
    element.replaceChildren(input);
    input.focus();

    if (type !== 'textarea' && input.select) {
        input.select();
    }

    let saving = false;

    const finish = async (save) => {
        if (element.dataset.editing !== '1' || saving) {
            return;
        }

        const nextValue = input.value.trim();
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
