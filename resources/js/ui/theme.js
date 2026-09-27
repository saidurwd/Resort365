/**
 * When the page offers <meta name="theme-save-url"> (a signed-in user), save colour-mode
 * changes made with the navbar toggle to the user's profile.
 */
export function initThemePersistence() {
    const url = document.querySelector('meta[name="theme-save-url"]')?.content;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!url || !token) {
        return;
    }

    document.addEventListener('changed.lte.color-mode', (event) => {
        fetch(url, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ theme: event.detail.theme }),
        }).catch(() => {});
    });
}
