<?php

namespace Modules\Accounting\Services;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Enums\PostingKey;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Payroll\Contracts\PoolCollections;
use Modules\Payroll\Enums\PoolKind;

/**
 * Payroll's `PoolCollections` from the ledger (Step 6.6): what was credited to Service charge payable or Tips payable for a
 * property between two dates, less what was taken back (a voided bill), leaving out the payroll entries that distribute it.
 */
class LedgerPoolCollections implements PoolCollections
{
    public function __construct(
        private readonly AccountResolver $accounts,
        private readonly TenantContext $tenant,
    ) {}

    public function collected(int $propertyId, string $from, string $to, PoolKind $kind): string
    {
        try {
            $account = $this->accounts->fixed($kind === PoolKind::Tips ? PostingKey::TipsPayable : PostingKey::ServiceChargePayable);
        } catch (AccountingRuleViolated) {
            return '0.00';
        }

        $tenantId = $this->tenant->tenantOrFail(self::class)->id;
        $row = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.tenant_id', $tenantId)->where('e.tenant_id', $tenantId)->where('l.account_id', $account)->where('l.property_id', $propertyId)
            ->whereIn('e.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])->whereBetween('e.entry_date', [$from, $to])
            ->where(fn ($query) => $query->whereNull('e.source_type')->orWhere('e.source_type', '!=', 'payroll_run'))
            ->selectRaw('COALESCE(SUM(l.credit), 0) as credit, COALESCE(SUM(l.debit), 0) as debit')->first();

        $net = $row !== null ? bcsub((string) $row->credit, (string) $row->debit, 2) : '0.00';

        return bccomp($net, '0', 2) > 0 ? $net : '0.00';
    }
}
