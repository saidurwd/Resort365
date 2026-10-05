import { getJson, live } from './live';

/**
 * The kitchen display board (ARCHITECTURE §10.3, Step 3.5): tickets in New · Preparing · Ready columns,
 * coloured by how long they have waited; a tap moves a ticket on (start → ready → bump; a void ticket is
 * acknowledged). The board is reloaded from the server whenever the station's channel says something
 * changed, and polled while the WebSocket is down.
 */
export function kdsBoard({ board, channel, urls, labels }) {
    return {
        board,
        labels,
        online: false,
        busy: null,
        error: '',
        offset: 0,
        clock: Date.now(),
        fresh: [],

        init() {
            this.sync(board);
            setInterval(() => {
                this.clock = Date.now();
            }, 15000);
            live({
                channel,
                on: { 'kot.sent': () => this.reload(true), 'kot.voided': () => this.reload(true), 'kot.status': () => this.reload() },
                poll: () => this.reload(true),
                onState: (state) => {
                    this.online = state;
                },
            });
        },

        sync(data, highlight = false) {
            const before = new Set(this.board.tickets.map((ticket) => ticket.id));
            this.offset = new Date(data.now).getTime() - Date.now();
            this.board = data;
            this.clock = Date.now();

            if (highlight) {
                this.fresh = data.tickets.filter((ticket) => !before.has(ticket.id)).map((ticket) => ticket.id);
                setTimeout(() => {
                    this.fresh = [];
                }, 4000);
            }
        },

        async reload(highlight = false) {
            const data = await getJson(urls.board);

            if (data?.board) {
                this.sync(data.board, highlight);
            }
        },

        column(status) {
            return this.board.tickets.filter((ticket) => (status === 'new' ? ticket.status === 'new' : ticket.status === status));
        },

        minutes(ticket) {
            return Math.max(0, Math.floor((this.clock + this.offset - new Date(ticket.fired_at).getTime()) / 60000));
        },

        age(ticket) {
            const minutes = this.minutes(ticket);

            if (ticket.type === 'void') {
                return 'kds-ticket--void';
            }

            return minutes >= this.board.late ? 'kds-ticket--late' : minutes >= this.board.warn ? 'kds-ticket--warn' : '';
        },

        nextAction(ticket) {
            if (ticket.type === 'void') {
                return 'bump';
            }

            return { new: 'start', preparing: 'ready', ready: 'bump' }[ticket.status];
        },

        nextLabel(ticket) {
            return ticket.type === 'void' ? this.labels.ack : this.labels[this.nextAction(ticket)];
        },

        async act(ticket, action) {
            this.busy = ticket.id;
            this.error = '';

            try {
                const response = await fetch(urls.progress.replace('__KOT__', ticket.id), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ action }),
                });
                const data = await response.json().catch(() => ({}));

                if (data.board) {
                    this.sync(data.board);
                }

                if (!response.ok) {
                    this.error = data.message || response.statusText;
                }
            } catch {
                this.error = this.labels.offline;
            } finally {
                this.busy = null;
            }
        },

        recall() {
            if (this.board.recall) {
                this.act({ id: this.board.recall.id }, 'recall');
            }
        },
    };
}
