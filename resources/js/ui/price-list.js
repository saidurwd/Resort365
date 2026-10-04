/**
 * An outlet's price list (Restaurant): the rows are edited in Alpine and sent as one JSON field
 * (rows_json), so a long menu fits in one request. The 86 switch on a saved row is saved at once.
 * The server checks everything again (SavePriceList, MarkSoldOut).
 */
export function priceList({ rows, soldOutUrl, canPrice, canSoldOut }) {
    return {
        rows,
        canPrice,
        canSoldOut,
        filter: '',
        onlyOnSale: false,
        bulkStation: '',
        message: '',

        get shown() {
            const term = this.filter.trim().toLowerCase();

            return this.rows.filter((row) => (!this.onlyOnSale || row.on_sale)
                && (term === '' || `${row.code} ${row.name} ${row.variant ?? ''} ${row.category}`.toLowerCase().includes(term)));
        },

        get json() {
            return JSON.stringify(this.rows.map((row) => ({
                item_id: row.item_id,
                variant_id: row.variant_id,
                on_sale: row.on_sale,
                price: row.on_sale ? String(row.price) : null,
                station_id: row.station_id ? Number(row.station_id) : null,
                is_available: row.is_available,
                is_package_eligible: row.is_package_eligible,
                schedule_ids: row.schedule_ids.map(Number),
            })));
        },

        applyStation() {
            this.shown.filter((row) => row.on_sale).forEach((row) => { row.station_id = this.bulkStation || null; });
        },

        toggleSchedule(row, id) {
            row.schedule_ids = row.schedule_ids.includes(id) ? row.schedule_ids.filter((other) => other !== id) : [...row.schedule_ids, id];
        },

        async soldOut(row) {
            const soldOut = row.is_available;

            if (!row.row_id || canPrice) {
                row.is_available = !soldOut;

                return;
            }

            const body = new FormData();
            body.append('sold_out', soldOut ? '1' : '0');
            const response = await fetch(soldOutUrl.replace('__ROW__', row.row_id), {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                body,
            });
            const data = await response.json().catch(() => ({}));

            if (response.ok && data.ok) {
                row.is_available = !data.sold_out;
                this.message = '';
            } else {
                this.message = data.message || response.statusText;
            }
        },
    };
}
