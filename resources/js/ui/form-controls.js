import TomSelect from 'tom-select';
import flatpickr from 'flatpickr';
import { readOptions } from './options';

/**
 * <select data-tom-select> → Tom Select; <input data-flatpickr> → flatpickr.
 * Options come from the attribute value as JSON (see resources/views/components/form).
 * `{"remote": url}` searches the server as the user types: GET url?q=term returning
 * [{"value": 1, "text": "Label"}, …].
 */
export function initFormControls(root = document) {
    root.querySelectorAll('select[data-tom-select]').forEach((select) => {
        if (select.tomselect) {
            return;
        }

        const { remote, ...options } = readOptions(select, 'data-tom-select');

        new TomSelect(select, {
            allowEmptyOption: true,
            maxOptions: null,
            plugins: select.multiple ? ['remove_button'] : [],
            ...(remote ? remoteOptions(remote) : {}),
            ...options,
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

function remoteOptions(url) {
    return {
        valueField: 'value',
        labelField: 'text',
        searchField: [],
        shouldLoad: (query) => query.trim().length >= 2,
        load(query, callback) {
            const separator = url.includes('?') ? '&' : '?';

            fetch(`${url}${separator}q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } })
                .then((response) => (response.ok ? response.json() : []))
                .then(callback)
                .catch(() => callback());
        },
    };
}
