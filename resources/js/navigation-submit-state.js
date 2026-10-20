// Back/Forward Cache restores the DOM exactly as it was when navigation began,
// including disabled buttons and visible spinners. Only track full-page submits;
// AJAX search buttons still belong to their request's finally handler.
export function createNavigationSubmitState(page) {
    const pending = new Map();

    page.addEventListener('pageshow', () => {
        for (const [button, previous] of pending) {
            button.disabled = previous.disabled;
            previous.spinner?.classList.toggle('d-none', previous.spinnerHidden);
            if (previous.busy === null) button.removeAttribute('aria-busy');
            else button.setAttribute('aria-busy', previous.busy);
        }
        pending.clear();
    });

    return {
        start(button) {
            if (!button || pending.has(button)) return;
            const spinner = button.querySelector('.spinner-border');
            pending.set(button, {
                disabled: button.disabled,
                spinner,
                spinnerHidden: spinner?.classList.contains('d-none'),
                busy: button.getAttribute('aria-busy'),
            });
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            spinner?.classList.remove('d-none');
        },
    };
}
