import * as bootstrap from 'bootstrap';
import 'admin-lte';
import Alpine from 'alpinejs';

import { initFormControls } from './ui/form-controls';
import { initDataTables } from './ui/datatables';
import { initConfirm } from './ui/confirm';
import { initHashTabs } from './ui/tabs';
import { initThemePersistence } from './ui/theme';
import { tapeChart } from './ui/tape-chart';
import { floorPlan } from './ui/floor-plan';
import { priceList } from './ui/price-list';
import { managerApproval, pinPad, posIdle } from './ui/pos';

// AdminLTE and inline markup rely on Bootstrap's global (e.g. `bootstrap.Modal`).
window.bootstrap = bootstrap;

window.Alpine = Alpine;
Alpine.data('tapeChart', tapeChart);
Alpine.data('floorPlan', floorPlan);
Alpine.data('priceList', priceList);
Alpine.data('pinPad', pinPad);
Alpine.data('managerApproval', managerApproval);
Alpine.data('posIdle', posIdle);
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

// A button that opens a modal with data-modal-action="url" points the modal's form at that URL
// (one modal shared by every row of a list, e.g. lost & found's "Return / dispose").
document.addEventListener('show.bs.modal', (event) => {
    const action = event.relatedTarget?.dataset?.modalAction;
    const form = action ? event.target.querySelector('form') : null;

    if (form) {
        form.action = action;
    }
});

document.addEventListener('DOMContentLoaded', () => {
    window.initUi(document);

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
        bootstrap.Tooltip.getOrCreateInstance(element);
    });
});
