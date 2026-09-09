export function normalizeDateInput(value) {
    const trimmed = String(value ?? '').trim();

    if (trimmed === '') {
        return '';
    }

    const isoMatch = trimmed.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
    const slashMatch = trimmed.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
    const match = isoMatch ?? slashMatch;

    if (! match) {
        return trimmed;
    }

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const date = new Date(year, month - 1, day);

    if (
        date.getFullYear() !== year
        || date.getMonth() !== month - 1
        || date.getDate() !== day
    ) {
        return trimmed;
    }

    return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function toDisplayValue(isoValue) {
    const normalized = normalizeDateInput(isoValue);

    if (normalized === '' || ! /^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
        return normalized;
    }

    const [year, month, day] = normalized.split('-');

    return `${year}/${Number(month)}/${Number(day)}`;
}

function syncTextFromPicker(root) {
    const textInput = root.querySelector('[data-date-text]');
    const picker = root.querySelector('[data-date-picker]');

    if (! textInput || ! picker) {
        return;
    }

    if (picker.value === '') {
        textInput.value = '';

        return;
    }

    textInput.value = toDisplayValue(picker.value);
}

function syncPickerFromText(root) {
    const textInput = root.querySelector('[data-date-text]');
    const picker = root.querySelector('[data-date-picker]');

    if (! textInput || ! picker) {
        return;
    }

    const normalized = normalizeDateInput(textInput.value);

    if (normalized === '' || ! /^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
        picker.value = '';

        return;
    }

    picker.value = normalized;
    textInput.value = toDisplayValue(normalized);
}

function openDatePicker(root) {
    const picker = root.querySelector('[data-date-picker]');

    if (! picker) {
        return;
    }

    syncPickerFromText(root);

    if (typeof picker.showPicker === 'function') {
        picker.showPicker();

        return;
    }

    picker.focus();
    picker.click();
}

export function initDateField(root) {
    if (! root || root.dataset.dateFieldBound === '1') {
        return;
    }

    root.dataset.dateFieldBound = '1';

    const textInput = root.querySelector('[data-date-text]');
    const picker = root.querySelector('[data-date-picker]');
    const trigger = root.querySelector('[data-date-trigger]');

    if (! textInput || ! picker) {
        return;
    }

    syncPickerFromText(root);

    if (textInput.value.trim() === '' && picker.value !== '') {
        syncTextFromPicker(root);
    }

    trigger?.addEventListener('click', (event) => {
        event.preventDefault();
        openDatePicker(root);
    });

    picker.addEventListener('change', () => {
        syncTextFromPicker(root);
    });

    picker.addEventListener('input', () => {
        syncTextFromPicker(root);
    });

    textInput.addEventListener('blur', () => {
        syncPickerFromText(root);
    });

    textInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            syncPickerFromText(root);
        }
    });
}

export function initDateFields(scope = document) {
    scope.querySelectorAll('[data-date-field]').forEach((root) => {
        initDateField(root);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initDateFields();
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    form.querySelectorAll('[data-date-field]').forEach((root) => {
        syncPickerFromText(root);
    });
});
