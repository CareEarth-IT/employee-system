import './bootstrap';
import './profile-inline-edit';

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    if (form.dataset.disableOnSubmit === 'false') {
        return;
    }

    if (form.dataset.submitting === '1') {
        event.preventDefault();
        return;
    }

    form.dataset.submitting = '1';

    const submitters = form.querySelectorAll('button[type="submit"], input[type="submit"]');
    submitters.forEach((el) => {
        if (el instanceof HTMLButtonElement || el instanceof HTMLInputElement) {
            el.disabled = true;
            el.classList.add('opacity-70', 'cursor-wait');
        }
        if (el instanceof HTMLButtonElement && form.dataset.submittingLabel) {
            el.textContent = form.dataset.submittingLabel;
        }
    });
});
