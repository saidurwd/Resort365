<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Enums\VoucherStatus;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\BankMatch;
use Modules\Accounting\Models\FundTransfer;
use Modules\Accounting\Models\JournalEntry;

/**
 * Voids a posted transfer by reversing its entry. A transfer already matched to a bank statement cannot be voided.
 */
class VoidTransfer extends Action
{
    public function __construct(private readonly ReverseJournalEntry $reverse) {}

    /**
     * @throws AccountingRuleViolated
     */
    public function handle(FundTransfer $transfer, string $reason, ?int $userId = null): FundTransfer
    {
        if (trim($reason) === '') {
            throw new AccountingRuleViolated(__('Give a reason for voiding the transfer.'));
        }

        return $this->transaction(function () use ($transfer, $reason, $userId): FundTransfer {
            $locked = FundTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($locked->status !== VoucherStatus::Posted) {
                throw new AccountingRuleViolated(__('Transfer :no is already void.', ['no' => $locked->transfer_no]));
            }

            $entry = JournalEntry::query()->with('lines')->findOrFail($locked->journal_entry_id);

            if (BankMatch::query()->whereIn('journal_line_id', $entry->lines->pluck('id'))->exists()) {
                throw new AccountingRuleViolated(__('Transfer :no is matched to a bank statement: unmatch it first.', ['no' => $locked->transfer_no]));
            }

            $this->reverse->handle($entry, null, $userId, __('Transfer :no voided: :reason', ['no' => $locked->transfer_no, 'reason' => $reason]));
            $locked->forceFill(['status' => VoucherStatus::Void, 'voided_by' => $userId, 'voided_at' => now(), 'void_reason' => mb_substr(trim($reason), 0, 300)])->save();

            return $locked;
        });
    }
}
