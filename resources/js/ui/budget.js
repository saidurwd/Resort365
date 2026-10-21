/**
 * The budget lines grid (Step 7.2): one row per income or expense account and department with an amount for each month. Totals
 * of a row and of a month are shown as you type; the server checks everything again when the form is saved.
 */
export function budgetGrid(initial) {
    return {
        rows: initial.map((row) => ({ ...row, months: row.months.map((amount) => String(amount)) })),
        add() {
            this.rows.push({ account_id: '', department_id: '', months: Array(12).fill('0') });
        },
        remove(index) {
            this.rows.splice(index, 1);
        },
        fill(index) {
            const first = this.rows[index].months[0];
            this.rows[index].months = Array(12).fill(first);
        },
        total(row) {
            return row.months.reduce((sum, amount) => sum + (parseFloat(amount) || 0), 0);
        },
        column(month, type) {
            return this.rows.filter((row) => this.typeOf(row.account_id) === type).reduce((sum, row) => sum + (parseFloat(row.months[month]) || 0), 0);
        },
        typeOf(accountId) {
            return (initial.accountTypes || {})[accountId] || '';
        },
        format(value) {
            return new Intl.NumberFormat('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
        },
    };
}
