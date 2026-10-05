import { posFetch, printTicket } from './pos';

/**
 * The POS bill screen (ARCHITECTURE §5.10.7, §10.3, §12 rule 15): discounts, the split with a live
 * preview from the server, printing, payments (cash with change, card, wallet, bank transfer, tips),
 * complimentary bills, reopening and voids. When the server answers that a manager's PIN would allow a
 * step, the screen asks for it and repeats the step with the approval. Every amount comes from the server.
 */
export function posBill(state) {
    return {
        ...state,
        busy: false,
        error: '',
        message: '',
        mode: 'none',
        count: 2,
        amounts: ['', ''],
        assign: {},
        bills: [],
        discounting: null,
        paying: null,
        exception: null,
        approval: null,

        init() {
            this.resetAssign();
            this.preview();
            this.$watch('mode', () => this.preview());
            this.$watch('count', () => {
                this.resetAssign();
                this.preview();
            });
        },

        get open() {
            return this.billing.status === 'open';
        },

        money(value) {
            return Number(value ?? 0).toFixed(2);
        },

        url(name, bill) {
            return this.urls[name].replace('__BILL__', bill);
        },

        resetAssign() {
            const assign = {};
            this.billing.lines.forEach((line) => {
                assign[line.id] = Array.from({ length: this.count }, (_, index) => (index === 0 ? line.quantity : 0));
            });
            this.assign = assign;
        },

        moveUnit(line, bill, step) {
            const units = [...this.assign[line.id]];
            const from = step > 0 ? units.findIndex((value, index) => index !== bill && value > 0) : -1;

            if (step > 0 && from >= 0) {
                units[from] -= 1;
                units[bill] += 1;
            } else if (step < 0 && units[bill] > 0) {
                units[bill] -= 1;
                units[(bill + 1) % units.length] += 1;
            }

            this.assign = { ...this.assign, [line.id]: units };
            this.preview();
        },

        options() {
            const options = { mode: this.mode };

            if (['equal', 'item'].includes(this.mode)) {
                options.bills = this.count;
            }

            if (this.mode === 'amount') {
                options.amounts = this.amounts;
            }

            if (this.mode === 'item') {
                options.assignments = Object.fromEntries(Object.entries(this.assign).map(([line, units]) => [line, Object.fromEntries(units.map((value, index) => [index, value]))]));
            }

            return options;
        },

        async preview() {
            if (!this.open) {
                return;
            }

            if (this.mode === 'none') {
                this.bills = this.billing.preview ? [this.billing.preview] : [];
                this.error = '';

                return;
            }

            const [ok, data] = await posFetch('POST', this.urls.preview, this.options(), this.messages.offline);
            this.bills = ok ? data.bills : [];
            this.error = ok ? '' : data.message;
        },

        restAmount() {
            const total = Number(this.billing.preview?.grand_total ?? 0);
            const given = this.amounts.slice(0, -1).reduce((sum, value) => sum + Number(value || 0), 0);
            this.amounts[this.amounts.length - 1] = Math.max(0, total - given).toFixed(2);
            this.preview();
        },

        async call(url, body, after = null, retry = null) {
            this.busy = true;
            this.error = '';
            const [ok, data] = await posFetch('POST', url, body, this.messages.offline);
            this.busy = false;

            if (data.billing) {
                this.billing = data.billing;
            }

            if (!ok && data.approval && retry) {
                // A manager's PIN would allow it: ask, then repeat with the approval.
                this.needApproval(data.approval, data.approval.startsWith('bill.comp') || data.approval.startsWith('bill.void') ? body.bill_id : this.billing.id, retry, data.message);

                return false;
            }

            if (!ok) {
                this.error = data.message;

                return false;
            }

            this.message = '';
            (data.print ?? []).forEach((link) => printTicket(link));
            this.preview();
            after?.(data);

            return true;
        },

        // Manager approval: ask, then repeat the step with the approval.
        needApproval(action, subjectId, retry, why) {
            this.approval = { action, subjectId, retry, why, managerId: null, pin: '', error: '' };
        },

        async approve() {
            const approval = this.approval;
            this.busy = true;
            const [ok, data] = await posFetch('POST', this.urls.approve, {
                action: approval.action, manager_id: approval.managerId, pin: approval.pin, subject_id: approval.subjectId,
            }, this.messages.offline);
            this.busy = false;
            approval.pin = '';

            if (!ok) {
                approval.error = data.message;

                return;
            }

            this.approval = null;
            await approval.retry(data.approval_id);
        },

        // Discounts
        startDiscount(line = null) {
            const current = line ? line.discount : this.billing.discount;
            this.discounting = { line, type: current?.type ?? 'percent', value: current?.value ?? '', reason: current?.reason ?? '' };
        },

        async saveDiscount(approvalId = null, remove = false) {
            const discount = this.discounting;
            const body = remove
                ? { line_id: discount.line?.id ?? null }
                : { line_id: discount.line?.id ?? null, type: discount.type, value: discount.value, reason: discount.reason, approval_id: approvalId };
            const ok = await this.call(this.urls.discount, body, null, remove ? null : (id) => this.saveDiscount(id));

            if (ok) {
                this.discounting = null;
            }
        },

        print() {
            return this.call(this.urls.print, this.options());
        },

        reopen(approvalId = null) {
            this.call(this.urls.reopen, { approval_id: approvalId }, () => {
                this.mode = 'none';
            }, (id) => this.reopen(id));
        },

        // Payments
        startPayment(bill) {
            this.paying = { bill, method: 'cash', amount: bill.due, tip: '', tendered: '', reference: '' };
        },

        get change() {
            if (!this.paying || this.paying.method !== 'cash' || this.paying.tendered === '') {
                return null;
            }

            return Number(this.paying.tendered) - Number(this.paying.amount || 0) - Number(this.paying.tip || 0);
        },

        quickTenders(due) {
            const amount = Number(due);
            const notes = [100, 500, 1000];
            const options = new Set([amount]);
            notes.forEach((note) => options.add(Math.ceil(amount / note) * note));

            return [...options].sort((a, b) => a - b).slice(0, 4);
        },

        async pay() {
            const payment = this.paying;
            const ok = await this.call(this.url('pay', payment.bill.id), {
                method: payment.method, amount: payment.amount, tip: payment.tip || 0,
                tendered: payment.method === 'cash' && payment.tendered !== '' ? payment.tendered : null, reference: payment.reference,
            });

            if (ok) {
                this.paying = null;
            }
        },

        reprint(url) {
            printTicket(url);
        },

        // Complimentary and void
        startException(kind, bill) {
            this.exception = { kind, bill, comp_reason: this.compReasons[0].value, note: '', reason: '', food_prepared: true };
        },

        async saveException(approvalId = null) {
            const exception = this.exception;
            const ok = await this.call(this.url(exception.kind, exception.bill.id), {
                bill_id: exception.bill.id, comp_reason: exception.comp_reason, note: exception.note, reason: exception.reason,
                food_prepared: exception.food_prepared, approval_id: approvalId,
            }, null, (id) => this.saveException(id));

            if (ok) {
                this.exception = null;
            }
        },
    };
}
