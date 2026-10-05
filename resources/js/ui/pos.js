import { confirmAction } from './confirm';
import { getJson, live } from './live';

/**
 * POS screen helpers (Restaurant, ARCHITECTURE §10.1). The server checks everything again; these only
 * make the touch screen pleasant.
 */

/** The lock screen's PIN pad: pick a person, type the PIN, send it. */
export function pinPad({ userId }) {
    return {
        userId: userId ? Number(userId) : null,
        pin: '',

        choose(id) {
            this.userId = id;
            this.pin = '';
        },

        press(digit) {
            if (this.pin.length < 6) {
                this.pin += digit;
            }
        },
    };
}

/**
 * Closing a POS session: shows the counted cash and difference as notes are entered, and when the
 * difference is larger than the limit asks a manager for their PIN before sending the form.
 */
export function managerApproval({ url, action, subjectId, expected, limit, values, why }) {
    return {
        counts: {},
        asking: false,
        managerId: null,
        pin: '',
        busy: false,
        error: '',
        why,

        get counted() {
            return values.reduce((sum, value) => sum + Number(value) * (Number(this.counts[value]) || 0), 0);
        },

        get variance() {
            return Math.round((this.counted - expected) * 100) / 100;
        },

        check(event) {
            if (Math.abs(this.variance) > limit && !this.$refs.approval.value) {
                event.preventDefault();
                this.asking = true;
            }
        },

        async approve() {
            this.busy = true;
            this.error = '';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ action, manager_id: this.managerId, pin: this.pin, subject_id: subjectId }),
                });
                const data = await response.json().catch(() => ({}));

                if (response.ok && data.ok) {
                    this.$refs.approval.value = data.approval_id;
                    this.asking = false;
                    this.$root.submit();

                    return;
                }

                this.error = data.message || Object.values(data.errors ?? {}).flat()[0] || response.statusText;
            } catch {
                this.error = 'No connection. Try again.';
            } finally {
                this.pin = '';
                this.busy = false;
            }
        },
    };
}

/** Locks the terminal (signs the person out) after some minutes without a touch or key. */
export function posIdle(minutes) {
    return {
        timer: null,

        init() {
            if (!minutes) {
                return;
            }

            const reset = () => {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => document.querySelector('[data-lock-form]')?.submit(), minutes * 60 * 1000);
            };

            ['pointerdown', 'keydown', 'scroll'].forEach((type) => window.addEventListener(type, reset, { passive: true }));
            reset();
        },
    };
}

/** Sends a JSON request to the POS API with the CSRF token; returns [ok, data]. */
async function posFetch(method, url, body, offline) {
    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await response.json().catch(() => ({}));

        if (response.ok && data.ok !== false) {
            return [true, data];
        }

        return [false, { message: data.message || Object.values(data.errors ?? {}).flat()[0] || response.statusText }];
    } catch {
        return [false, { message: offline }];
    }
}

/** Prints a kitchen ticket without leaving the order: loads it in a hidden frame that prints itself. */
function printTicket(url) {
    const frame = document.createElement('iframe');
    frame.className = 'pos-print-frame';
    frame.setAttribute('aria-hidden', 'true');
    frame.src = url + (url.includes('?') ? '&' : '?') + 'auto=1';
    document.body.appendChild(frame);
    setTimeout(() => frame.remove(), 60000);
}

/**
 * The POS order screen (ARCHITECTURE §5.10.5, §12 rule 15): the menu grid, the item options sheet
 * (variant, modifiers, quantity, course, seat, hold, notes), the ticket, Send / Fire, voids with a
 * manager's PIN, transfer, merge and cancel. Every change goes to the server, which prices it and
 * answers with the whole order; nothing is totalled here.
 */
export function posOrder(state) {
    return {
        ...state,
        category: null,
        search: '',
        picking: null,
        selected: null,
        voiding: null,
        tool: null,
        busy: false,
        message: '',
        error: '',
        managerId: null,
        pin: '',
        online: false,
        polls: 0,

        init() {
            live({
                channel: this.channel,
                on: {
                    'kot.status': (event) => event.order_id === this.order.id && this.reloadOrder(),
                    'table.status': () => this.reloadOrder(),
                    'menu.changed': () => this.reloadMenu(),
                },
                poll: () => {
                    this.reloadOrder();
                    // The menu changes rarely: reload it every sixth poll.
                    if (this.polls++ % 6 === 0) {
                        this.reloadMenu();
                    }
                },
                onState: (state) => {
                    this.online = state;
                },
            });
        },

        async reloadOrder() {
            if (this.busy) {
                return;
            }

            const data = await getJson(this.urls.order);

            if (data?.order) {
                if (data.order.status !== 'open') {
                    window.location = this.urls.floor;

                    return;
                }

                this.order = data.order;
            }
        },

        async reloadMenu() {
            const data = await getJson(this.urls.menu);

            if (data?.menu) {
                this.menu = data.menu;
            }
        },

        get items() {
            const term = this.search.trim().toLowerCase();

            return this.menu.items.filter((item) => (this.category === null || item.category_id === this.category)
                && (term === '' || item.name.toLowerCase().includes(term) || String(item.code).toLowerCase().includes(term)));
        },

        get lines() {
            return this.order.lines;
        },

        lineUrl(line, suffix = '') {
            return `${this.urls.base}/${line.id}${suffix}`;
        },

        variantFor(item) {
            return item.variants.find((variant) => !variant.sold_out && !variant.out_of_schedule) ?? item.variants[0];
        },

        unavailable(item) {
            return item.variants.every((variant) => variant.sold_out || variant.out_of_schedule);
        },

        pick(item) {
            if (this.unavailable(item)) {
                this.error = (item.variants.some((variant) => variant.sold_out) ? this.messages.soldOut : this.messages.notServed).replace(':item', item.name);

                return;
            }

            const choice = { item, variantId: this.variantFor(item).id, modifiers: {}, quantity: 1, notes: '', course: item.course, seat: '', held: false, openPrice: '' };

            if (item.variants.length === 1 && item.groups.length === 0 && !item.open) {
                this.add(choice);

                return;
            }

            this.picking = choice;
        },

        chosen(group) {
            return this.picking.modifiers[group.id] ?? [];
        },

        toggleModifier(group, option) {
            const chosen = [...this.chosen(group)];
            const at = chosen.indexOf(option.id);

            if (at >= 0) {
                chosen.splice(at, 1);
            } else if (group.max === 1) {
                chosen.splice(0, chosen.length, option.id);
            } else if (group.max === 0 || chosen.length < group.max) {
                chosen.push(option.id);
            }

            this.picking.modifiers = { ...this.picking.modifiers, [group.id]: chosen };
        },

        async call(method, url, body) {
            this.busy = true;
            this.error = '';
            const [ok, data] = await posFetch(method, url, body, this.messages.offline);
            this.busy = false;

            if (!ok) {
                this.error = data.message;

                return false;
            }

            if (data.redirect) {
                window.location = data.redirect;

                return true;
            }

            this.order = data.order;
            this.message = data.message ?? '';
            data.order.kots.filter((kot) => kot.print).forEach((kot) => printTicket(kot.url));

            return true;
        },

        async add(choice) {
            const ok = await this.call('POST', this.urls.base, {
                item_id: choice.item.id,
                variant_id: choice.variantId,
                quantity: choice.quantity,
                modifier_ids: Object.values(choice.modifiers).flat(),
                course: choice.course,
                seat: choice.seat === '' ? null : Number(choice.seat),
                notes: choice.notes,
                held: choice.held,
                open_price: choice.item.open ? choice.openPrice : null,
            });

            if (ok) {
                this.picking = null;
            }
        },

        async change(line, changes) {
            await this.call('PATCH', this.lineUrl(line), changes);
            this.selected = this.order.lines.find((item) => item.id === line.id) ?? null;
        },

        async remove(line) {
            if (await this.call('DELETE', this.lineUrl(line))) {
                this.selected = null;
            }
        },

        send(course = null) {
            return this.call('POST', this.urls.send, course ? { course } : {});
        },

        startVoid(line) {
            this.selected = null;
            this.voiding = { line, reason: this.reasons[0].value, note: '', wastage: false, asking: false };
            this.managerId = null;
            this.pin = '';
        },

        async confirmVoid(approvalId = null) {
            const voiding = this.voiding;

            if (!this.mayVoid && approvalId === null) {
                voiding.asking = true;

                return;
            }

            const ok = await this.call('POST', this.lineUrl(voiding.line, '/void'), {
                reason: voiding.reason, note: voiding.note, wastage: voiding.wastage, approval_id: approvalId,
            });

            if (ok) {
                this.voiding = null;
            }
        },

        async approveVoid() {
            this.busy = true;
            const [ok, data] = await posFetch('POST', this.urls.approve, {
                action: 'order.void-line', manager_id: this.managerId, pin: this.pin, subject_id: this.voiding.line.id, reason: this.voiding.note,
            }, this.messages.offline);
            this.busy = false;
            this.pin = '';

            if (!ok) {
                this.error = data.message;

                return;
            }

            await this.confirmVoid(data.approval_id);
        },

        async transfer(tableId) {
            if (await this.call('POST', this.urls.transfer, { table_id: tableId })) {
                window.location.reload();
            }
        },

        async merge(orderId) {
            if (await this.call('POST', this.urls.merge, { order_id: orderId })) {
                this.tool = null;
                this.otherOrders = this.otherOrders.filter((other) => other.id !== orderId);
            }
        },

        async cancel() {
            if (await confirmAction({ title: this.messages.cancel, confirmText: this.messages.cancelYes, cancelText: this.messages.keep, variant: 'danger' })) {
                await this.call('POST', this.urls.cancel, {});
            }
        },
    };
}
