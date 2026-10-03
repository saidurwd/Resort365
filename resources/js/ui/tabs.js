import { Tab } from 'bootstrap';

/**
 * Tabs that follow the URL hash: `<ul class="nav nav-tabs" data-hash-tabs>` whose buttons have
 * `data-bs-toggle="tab" data-bs-target="#tab-guests" data-tab-hash="guests"`. Opening
 * /page#guests shows that tab, and switching tabs updates the hash, so a form that redirects back
 * to /page#guests lands on the same tab. Pane ids differ from the hash so the page does not jump.
 */
export function initHashTabs(root = document) {
    root.querySelectorAll('[data-hash-tabs]').forEach((nav) => {
        if (nav.dataset.hashTabsReady) {
            return;
        }

        nav.dataset.hashTabsReady = '1';

        const showFromHash = () => {
            const hash = window.location.hash.replace('#', '');
            const trigger = hash && nav.querySelector(`[data-tab-hash="${CSS.escape(hash)}"]`);

            if (trigger) {
                Tab.getOrCreateInstance(trigger).show();
            }
        };

        nav.querySelectorAll('[data-tab-hash]').forEach((trigger) => {
            trigger.addEventListener('shown.bs.tab', () => {
                history.replaceState(null, '', `#${trigger.dataset.tabHash}`);
            });
        });

        window.addEventListener('hashchange', showFromHash);
        showFromHash();
    });
}
