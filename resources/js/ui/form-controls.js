import TomSelect from 'tom-select';
import flatpickr from 'flatpickr';
import { readOptions } from './options';

/**
 * <select data-tom-select> → Tom Select; <input data-flatpickr> → flatpickr.
 * Options come from the attribute value as JSON (see resources/views/components/form).
 */
export function initFormControls(root = document) {
    root.querySelectorAll('select[data-tom-select]').forEach((select) => {
        if (select.tomselect) {
            return;
        }

        new TomSelect(select, {
            allowEmptyOption: true,
            maxOptions: null,
            plugins: select.multiple ? ['remove_button'] : [],
            ...readOptions(select, 'data-tom-select'),
        });
    });

    root.querySelectorAll('input[data-flatpickr]').forEach((input) => {
        if (input._flatpickr) {
            return;
        }

        flatpickr(input, {
            allowInput: true,
            altInput: true,
            altFormat: 'd M Y',
            dateFormat: 'Y-m-d',
            ...readOptions(input, 'data-flatpickr'),
        });
    });
}
