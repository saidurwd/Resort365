import * as bootstrap from 'bootstrap';
import 'admin-lte';
import Alpine from 'alpinejs';

import { initFormControls } from './ui/form-controls';
import { initDataTables } from './ui/datatables';
import { initConfirm } from './ui/confirm';
import { initHashTabs } from './ui/tabs';
import { initThemePersistence } from './ui/theme';
import { tapeChart } from './ui/tape-chart';

// AdminLTE and inline markup rely on Bootstrap's global (e.g. `bootstrap.Modal`).
window.bootstrap = bootstrap;

window.Alpine = Alpine;
Alpine.data('tapeChart', tapeChart);
Alpine.start();

/**
 * Initialise data-attribute widgets inside `root`. Call again after inserting
 * server-rendered HTML (e.g. a modal body loaded over fetch).
 */
window.initUi = (root = document) => {
    initFormControls(root);
    initDataTables(root);
    initHashTabs(root);

    root.querySelectorAll('.modal[data-show-on-load]').forEach((modal) => {
        bootstrap.Modal.getOrCreateInstance(modal).show();
    });
};

initConfirm();
initThemePersistence();

document.addEventListener('DOMContentLoaded', () => {
    window.initUi(document);

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
        bootstrap.Tooltip.getOrCreateInstance(element);
    });
});
