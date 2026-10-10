<?php

namespace Modules\Accounting\Services;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\DTOs\AccountActivity;
use Modules\Accounting\DTOs\ReportFilter;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Models\Account;

/**
 * Sums the ledger for the financial reports (Step 4.5): per account the balance before the period and the
 * period's debits and credits, optionally per property. Reversed entries count (their reversals are lines of
 * their own); drafts never do.
 */
class LedgerQuery
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * Every account of the chart with its figures; `$byProperty` adds the period's net per property id ('none' = no property).
     *
     * @return list<AccountActivity>
     */
    public function activity(ReportFilter $filter, bool $byProperty = false): array
    {
        $before = $this->sums($filter, ['l.account_id'])->where('e.entry_date', '<', $filter->from)->groupBy('l.account_id')->get()->keyBy('account_id');
        $period = $this->sums($filter, ['l.account_id'])->whereBetween('e.entry_date', [$filter->from, $filter->to])->groupBy('l.account_id')->get()->keyBy('account_id');
        $columns = [];

        if ($byProperty) {
            foreach ($this->sums($filter, ['l.account_id', 'l.property_id'])->whereBetween('e.entry_date', [$filter->from, $filter->to])->groupBy('l.account_id', 'l.property_id')->get() as $row) {
                $columns[(int) $row->account_id][$row->property_id === null ? 'none' : (string) $row->property_id] = bcsub((string) $row->debit, (string) $row->credit, 2);
            }
        }

        return Account::query()->orderBy('code')->get()->map(function (Account $account) use ($before, $period, $columns): AccountActivity {
            $b = $before->get($account->id);
            $p = $period->get($account->id);

            return new AccountActivity(
                $account->id, $account->code, $account->name, $account->type, $account->parent_id, $account->is_group, $account->usali_department,
                $b !== null ? bcsub((string) $b->debit, (string) $b->credit, 2) : '0.00', $p !== null ? $this->money($p->debit) : '0.00', $p !== null ? $this->money($p->credit) : '0.00', $columns[$account->id] ?? [],
            );
        })->all();
    }

    /**
     * Balance of one account (debit less credit) before a date, with the same filters.
     */
    public function balanceBefore(ReportFilter $filter, int $accountId): string
    {
        $row = $this->sums($filter, [])->where('l.account_id', $accountId)->where('e.entry_date', '<', $filter->from)->first();

        return $row !== null ? bcsub($this->money($row->debit), $this->money($row->credit), 2) : '0.00';
    }

    /**
     * The posted lines of one account in the period, oldest first.
     *
     * @return list<object{id: int, entry_id: int, entry_no: string|null, entry_date: string, description: string, line_description: string|null, reference: string|null, debit: string, credit: string, property_id: int|null}>
     */
    public function lines(ReportFilter $filter, int $accountId, int $limit = 5000): array
    {
        return $this->base($filter)->where('l.account_id', $accountId)->whereBetween('e.entry_date', [$filter->from, $filter->to])
            ->orderBy('e.entry_date')->orderBy('e.id')->orderBy('l.line_no')->limit($limit)
            ->get(['l.id', 'e.id as entry_id', 'e.entry_no', 'e.entry_date', 'e.description', 'l.description as line_description', 'e.reference', 'l.debit', 'l.credit', 'l.property_id'])->all();
    }

    /**
     * @param  list<string>  $columns  the grouping columns to select besides the sums
     */
    private function sums(ReportFilter $filter, array $columns): Builder
    {
        return $this->base($filter)->select($columns)->selectRaw('COALESCE(SUM(l.debit), 0) as debit, COALESCE(SUM(l.credit), 0) as credit');
    }

    private function base(ReportFilter $filter): Builder
    {
        return DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.tenant_id', $this->tenant->tenantOrFail(self::class)->id)->where('e.tenant_id', $this->tenant->tenantOrFail(self::class)->id)
            ->whereIn('e.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
            ->when($filter->propertyId() !== null, fn (Builder $query) => $query->where('l.property_id', $filter->propertyId()))
            ->when($filter->withoutProperty(), fn (Builder $query) => $query->whereNull('l.property_id'))
            ->when($filter->departmentId !== null, fn (Builder $query) => $query->where('l.department_id', $filter->departmentId))
            ->when($filter->partyType !== null, fn (Builder $query) => $query->where('l.party_type', $filter->partyType))
            ->when($filter->partyId !== null, fn (Builder $query) => $query->where('l.party_id', $filter->partyId));
    }

    private function money(mixed $value): string
    {
        return bcadd((string) $value, '0', 2);
    }
}
