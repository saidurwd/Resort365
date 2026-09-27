import Swal from 'sweetalert2';

function currentTheme() {
    return document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'bootstrap-5-dark' : 'bootstrap-5-light';
}

/**
 * Ask the user to confirm an action. Resolves to true when confirmed.
 */
export async function confirmAction({
    title = 'Are you sure?',
    text = '',
    confirmText = 'Yes, continue',
    cancelText = 'Cancel',
    variant = 'primary',
} = {}) {
    const result = await Swal.fire({
        title,
        text,
        icon: variant === 'danger' ? 'warning' : 'question',
        theme: currentTheme(),
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        reverseButtons: true,
        focusCancel: variant === 'danger',
        buttonsStyling: false,
        customClass: {
            confirmButton: `btn btn-${variant} ms-2`,
            cancelButton: 'btn btn-outline-secondary',
        },
    });

    return result.isConfirmed;
}

function optionsFrom(element) {
    return {
        title: element.dataset.confirm || undefined,
        text: element.dataset.confirmText || undefined,
        confirmText: element.dataset.confirmButton || undefined,
        cancelText: element.dataset.confirmCancel || undefined,
        variant: element.dataset.confirmVariant || undefined,
    };
}

/**
 * Global confirm dialog: add data-confirm="Title" to a <form>, or to a link or button
 * outside a form. Optional: data-confirm-text, data-confirm-button, data-confirm-cancel,
 * data-confirm-variant (primary | danger | warning ...).
 */
export function initConfirm() {
    window.confirmAction = confirmAction;

    document.addEventListener('submit', async (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm') || form.dataset.confirmed === '1') {
            return;
        }

        event.preventDefault();

        if (await confirmAction(optionsFrom(form))) {
            form.dataset.confirmed = '1';
            form.requestSubmit(event.submitter ?? undefined);
            delete form.dataset.confirmed;
        }
    });

    document.addEventListener('click', async (event) => {
        const trigger = event.target instanceof Element ? event.target.closest('a[data-confirm], button[data-confirm]') : null;

        if (!trigger || trigger.closest('form[data-confirm]')) {
            return;
        }

        event.preventDefault();

        if (!(await confirmAction(optionsFrom(trigger)))) {
            return;
        }

        if (trigger instanceof HTMLAnchorElement) {
            window.location.assign(trigger.href);
        } else if (trigger.form) {
            trigger.form.requestSubmit(trigger);
        }
    });
}
