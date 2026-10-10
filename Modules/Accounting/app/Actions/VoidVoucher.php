<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Enums\VoucherStatus;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\Voucher;

/**
 * Voids a posted voucher by reversing its journal entry (the original stays, as posted entries do).
 */
class VoidVoucher extends Action
{
    public function __construct(private readonly ReverseJournalEntry $reverse) {}

    /**
     * @throws AccountingRuleViolated
     */
    public function handle(Voucher $voucher, string $reason, ?string $date = null, ?int $userId = null): Voucher
    {
        if (trim($reason) === '') {
            throw new AccountingRuleViolated(__('Give a reason for voiding the voucher.'));
        }

        return $this->transaction(function () use ($voucher, $reason, $date, $userId): Voucher {
            $locked = Voucher::query()->lockForUpdate()->findOrFail($voucher->id);

            if ($locked->status !== VoucherStatus::Posted) {
                throw new AccountingRuleViolated(__('Voucher :no is already void.', ['no' => $locked->voucher_no]));
            }

            $entry = JournalEntry::query()->findOrFail($locked->journal_entry_id);
            $this->reverse->handle($entry, $date, $userId, __('Voucher :no voided: :reason', ['no' => $locked->voucher_no, 'reason' => $reason]));
            $locked->forceFill(['status' => VoucherStatus::Void, 'voided_by' => $userId, 'voided_at' => now(), 'void_reason' => mb_substr(trim($reason), 0, 300)])->save();

            return $locked;
        });
    }
}
