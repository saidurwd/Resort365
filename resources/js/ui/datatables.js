import DataTable from 'datatables.net-bs5';
import { readOptions } from './options';

/**
 * <table data-datatable='{"ajax": "...", "columns": [...]}'> → server-side DataTable.
 * The endpoint returns yajra/laravel-datatables JSON.
 */
export function initDataTables(root = document) {
    root.querySelectorAll('table[data-datatable]').forEach((table) => {
        if (DataTable.isDataTable(table)) {
            return;
        }

        const options = readOptions(table, 'data-datatable');

        new DataTable(table, {
            processing: true,
            serverSide: true,
            responsive: true,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            language: {
                emptyTable: table.dataset.emptyText ?? 'No records found.',
            },
            ...options,
        });
    });
}
