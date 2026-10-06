import { getJson, live } from './live';
import { posFetch } from './pos';

/**
 * The POS floor kept live (Step 3.5): table colours, running totals and open orders reload whenever
 * the outlet's channel says the floor changed, or every few seconds without WebSockets.
 */
export function posFloor({ floor, channel, urls }) {
    return {
        floor,
        area: null,
        table: null,
        online: false,
        mode: null,
        term: '',
        stays: [],
        stay: null,
        error: '',

        init() {
            live({
                channel,
                on: { 'table.status': () => this.reload(), 'kot.status': () => this.reload(), 'delivery.status': () => this.reload() },
                poll: () => this.reload(),
                onState: (state) => {
                    this.online = state;
                },
            });
        },

        async reload() {
            const data = await getJson(urls.floor);

            if (data?.floor) {
                this.floor = data.floor;
            }
        },

        state(id) {
            return this.floor.tables[id] ?? { status: 'available', order_url: null, subtotal: null, minutes: null };
        },

        // Room service, deliveries and staff meals (Step 3.8)
        start(mode) {
            this.mode = this.mode === mode ? null : mode;
            this.table = null;
            this.stay = null;
            this.error = '';

            if (mode === 'room') {
                this.searchStays('');
            }
        },

        async searchStays(term) {
            const data = await getJson(`${urls.stays}?term=${encodeURIComponent(term ?? '')}`);
            this.stays = data?.stays ?? [];
        },

        async advance(order, status) {
            this.error = '';
            const [ok, data] = await posFetch('POST', order.delivery.url, { status }, 'No connection. Try again.');

            if (ok && data.floor) {
                this.floor = data.floor;
            } else if (!ok) {
                this.error = data.message;
            }
        },

        nextDelivery(order) {
            return { ordered: 'out_for_delivery', preparing: 'out_for_delivery', out_for_delivery: 'delivered' }[order.delivery.status] ?? null;
        },

        pick(id, number, seats) {
            const order = this.state(id).order_url;

            if (order) {
                window.location = order;
            } else {
                this.table = { id, number, seats };
            }
        },
    };
}

/**
 * "Ready to serve" notices on every POS screen of the outlet (Step 3.5): the kitchen marked a ticket
 * ready; the waiter who took the order sees theirs highlighted. Without WebSockets the screen asks the
 * server for dishes made ready since it last looked. 86 changes are announced too.
 */
export function posReady({ channel, urls, userId, labels }) {
    return {
        notices: [],
        seen: new Set(),
        since: new Date().toISOString(),

        init() {
            live({
                channel,
                on: {
                    'kot.status': (event) => {
                        if (event.status === 'ready') {
                            this.add(event);
                        }
                    },
                    'menu.changed': (event) => {
                        if (event.message) {
                            this.notify({ key: `menu-${Date.now()}`, title: event.message, mine: false, icon: 'bi-slash-circle' });
                        }
                    },
                },
                poll: () => this.poll(),
            });
        },

        async poll() {
            const data = await getJson(`${urls.ready}?since=${encodeURIComponent(this.since)}`);

            if (data?.ready) {
                this.since = data.now;
                data.ready.forEach((event) => this.add(event));
            }
        },

        add(event) {
            if (this.seen.has(event.kot_id)) {
                return;
            }

            this.seen.add(event.kot_id);
            const where = event.table ? `${labels.table} ${event.table}` : event.order_no;
            this.notify({
                key: `kot-${event.kot_id}`,
                title: `${where}: ${labels.ready}`,
                body: event.items.join(', '),
                mine: Number(event.waiter_id) === Number(userId),
                icon: 'bi-bell',
                url: urls.order.replace('__ORDER__', event.order_id),
            });
        },

        notify(notice) {
            this.notices.push(notice);
            setTimeout(() => this.dismiss(notice.key), notice.mine ? 60000 : 20000);
        },

        dismiss(key) {
            this.notices = this.notices.filter((notice) => notice.key !== key);
        },
    };
}
