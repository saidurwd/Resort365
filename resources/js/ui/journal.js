/**
 * The journal entry's lines editor (Accounting, ARCHITECTURE §5.14): add and remove lines, show the totals and
 * whether the entry balances as figures are typed. Display only: the server checks every rule again when
 * the entry is saved or posted.
 */
export function journalLines({ lines }) {
    return {
        lines,

        add() {
            this.lines.push({ account_id: '', debit: '', credit: '', description: '', property_id: '', department_id: '', party_type: '', party_id: '', open: false });
        },

        remove(index) {
            if (this.lines.length > 2) {
                this.lines.splice(index, 1);
            }
        },

        // Typing a debit clears the credit of the same line, and the other way round.
        debited(line) {
            if (Number(line.debit) > 0) {
                line.credit = '';
            }
        },

        credited(line) {
            if (Number(line.credit) > 0) {
                line.debit = '';
            }
        },

        sum(field) {
            return Math.round(this.lines.reduce((total, line) => total + (Number(line[field]) || 0), 0) * 100) / 100;
        },

        get debit() {
            return this.sum('debit');
        },

        get credit() {
            return this.sum('credit');
        },

        get difference() {
            return Math.round((this.debit - this.credit) * 100) / 100;
        },

        get balanced() {
            return this.debit > 0 && this.difference === 0;
        },

        money(value) {
            return Number(value).toFixed(2);
        },
    };
}
