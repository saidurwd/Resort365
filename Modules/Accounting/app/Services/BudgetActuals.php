<?php

namespace Modules\Accounting\Services;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\JournalStatus;

/**
 * What the ledger shows for the income and expense accounts of a fiscal year, by account, department and month (Step 7.2): the
 * posted lines (a reversed entry's lines count, its reversal being a line of its own). Income is credit less debit and expense
 * debit less credit, so a normal balance is positive. Month 1 is the year's first period.
 */
class BudgetActuals
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @return array<string, array<int, string>> BudgetKey => month => amount
     */
    public function forYear(int $fiscalYearId, ?int $propertyId): array
    {
        $tenantId = $this->tenant->tenantOrFail(self::class)->id;
        $rows = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->join('fiscal_periods as p', 'p.id', '=', 'e.fiscal_period_id')->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('l.tenant_id', $tenantId)->where('e.tenant_id', $tenantId)->where('p.fiscal_year_id', $fiscalYearId)->whereIn('e.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
            ->whereIn('a.type', ['income', 'expense'])->when($propertyId !== null, fn ($query) => $query->where('l.property_id', $propertyId))
            ->groupBy('l.account_id', 'l.department_id', 'p.number', 'a.type')
            ->get(['l.account_id', 'l.department_id', 'p.number', 'a.type', DB::raw('COALESCE(SUM(l.debit), 0) as debit'), DB::raw('COALESCE(SUM(l.credit), 0) as credit')]);
        $actuals = [];

        foreach ($rows as $row) {
            $key = BudgetKey::of((int) $row->account_id, $row->department_id !== null ? (int) $row->department_id : null);
            $amount = $row->type === 'income' ? bcsub((string) $row->credit, (string) $row->debit, 2) : bcsub((string) $row->debit, (string) $row->credit, 2);
            $actuals[$key][(int) $row->number] = bcadd($actuals[$key][(int) $row->number] ?? '0', $amount, 2);
        }

        return $actuals;
    }
}
