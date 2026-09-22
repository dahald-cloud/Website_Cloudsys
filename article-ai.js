(() => {
    'use strict';

    document.querySelectorAll('[data-confirm]').forEach((button) => {
        button.addEventListener('click', (event) => {
            const message = button.getAttribute('data-confirm');
            if (message && !window.confirm(message)) event.preventDefault();
        });
    });

    document.querySelectorAll('[data-schedule]').forEach((button) => {
        button.addEventListener('click', (event) => {
            const form = button.form;
            const field = form ? form.querySelector('input[name="schedule_at"]') : null;
            if (field && !field.value) {
                event.preventDefault();
                field.setCustomValidity('Choose a UTC publication date and time.');
                field.reportValidity();
                field.addEventListener('input', () => field.setCustomValidity(''), {once: true});
            }
        });
    });
})();
