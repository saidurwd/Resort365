<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Enums\ChequeStatus;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\Voucher;

/**
 * A cheque bounced (Step 4.4): its voucher is voided, which reverses the ledger entry, and the cheque marked bounced.
 * Only a pending cheque can bounce; one the bank cleared has a statement match.
 */
class MarkChequeBounced extends Action
{
    public function __construct(private readonly VoidVoucher $void) {}

    /**
     * @throws AccountingRuleViolated
     */
    public function handle(Voucher $voucher, string $reason, ?int $userId = null): Voucher
    {
        return $this->transaction(function () use ($voucher, $reason, $userId): Voucher {
            $locked = Voucher::query()->lockForUpdate()->findOrFail($voucher->id);

            if ($locked->cheque_status !== ChequeStatus::Pending) {
                throw new AccountingRuleViolated(__('Only a pending cheque can be marked bounced.'));
            }

            $this->void->handle($locked, __('Cheque :no bounced: :reason', ['no' => $locked->cheque_no, 'reason' => $reason]), null, $userId);
            $locked->refresh()->forceFill(['cheque_status' => ChequeStatus::Bounced])->save();

            return $locked;
        });
    }
}
