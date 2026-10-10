<?php

namespace Modules\Accounting\Services;

use App\Support\Tenancy\PropertyContext;
use Modules\Accounting\Enums\ChequeStatus;
use Modules\Accounting\Models\Voucher;

/**
 * Keeps a voucher's cheque status in step with the bank matches of its journal entry (Step 4.4).
 */
class ChequeTracker
{
    public function cleared(int $journalEntryId): void
    {
        $this->set($journalEntryId, ChequeStatus::Pending, ChequeStatus::Cleared);
    }

    public function pending(int $journalEntryId): void
    {
        $this->set($journalEntryId, ChequeStatus::Cleared, ChequeStatus::Pending);
    }

    private function set(int $journalEntryId, ChequeStatus $from, ChequeStatus $to): void
    {
        app(PropertyContext::class)->unrestricted(fn () => Voucher::query()->where('journal_entry_id', $journalEntryId)->where('cheque_status', $from->value)->update(['cheque_status' => $to->value]));
    }
}
