<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\PaymentMethod;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosPayment;
use Modules\Restaurant\Models\PosSession;
use Modules\Restaurant\Services\BillSettlement;

/**
 * Takes a payment on a printed bill in the terminal's open session (ARCHITECTURE §5.10.7, §5.10.11): an
 * amount towards what is still due (never more), a tip on top, and for cash what was tendered and the
 * change. Several payments may settle one bill (cash + card); the last one settles it.
 * TODO(step-3.7): charge to room, city ledger and meal plans.
 */
class TakePayment extends Action
{
    public function __construct(
        private readonly BillSettlement $settlement,
    ) {}

    /**
     * @throws PosNotAllowed
     */
    public function handle(PosBill $bill, PaymentMethod $method, string $amount, string $tip, ?string $tendered, ?string $reference, PosSession $session, int $userId): PosPayment
    {
        if (! in_array($method, PaymentMethod::tenders(), true)) {
            throw new PosNotAllowed(__(':method is not taken here yet.', ['method' => $method->label()]));
        }

        return $this->transaction(function () use ($bill, $method, $amount, $tip, $tendered, $reference, $session, $userId): PosPayment {
            $locked = PosBill::query()->lockForUpdate()->findOrFail($bill->id);
            $open = PosSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $open->isOpen() || $open->outlet_id !== $locked->outlet_id) {
                throw new PosNotAllowed(__('Open a cash session on this terminal to take payments.'));
            }

            if ($locked->status !== BillStatus::Printed) {
                throw new PosNotAllowed(__('Bill :no is :status.', ['no' => $locked->bill_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            $amount = BigDecimal::of($amount)->toScale(2);
            $tip = BigDecimal::of($tip === '' ? '0' : $tip)->toScale(2);
            $due = BigDecimal::of($locked->grand_total)->minus($locked->paid_total);

            if ($amount->isNegativeOrZero() || $tip->isNegative()) {
                throw new PosNotAllowed(__('Enter the amount paid.'));
            }

            if ($amount->isGreaterThan($due)) {
                throw new PosNotAllowed(__('Only :due is still due on this bill; enter anything more as a tip.', ['due' => (string) $due]));
            }

            $change = BigDecimal::zero();

            if ($method === PaymentMethod::Cash) {
                $given = BigDecimal::of($tendered ?? (string) $amount->plus($tip));

                if ($given->isLessThan($amount->plus($tip))) {
                    throw new PosNotAllowed(__('The cash given is less than the payment and tip.'));
                }

                $change = $given->minus($amount)->minus($tip);
            } elseif (trim((string) $reference) === '' && $method !== PaymentMethod::Wallet) {
                throw new PosNotAllowed(__('Enter the :method reference (approval code or last digits).', ['method' => mb_strtolower($method->label())]));
            }

            $payment = PosPayment::query()->create([
                'property_id' => $locked->property_id, 'outlet_id' => $locked->outlet_id, 'pos_bill_id' => $locked->id, 'pos_session_id' => $open->id,
                'business_date' => $open->business_date, 'method' => $method, 'amount' => (string) $amount, 'tip' => (string) $tip,
                'tendered' => $method === PaymentMethod::Cash ? (string) $amount->plus($tip)->plus($change) : null, 'change_given' => (string) $change,
                'reference' => trim((string) $reference) ?: null, 'created_by' => $userId,
            ]);

            $locked->forceFill(['paid_total' => (string) BigDecimal::of($locked->paid_total)->plus($amount), 'tip_total' => (string) BigDecimal::of($locked->tip_total)->plus($tip)])->save();
            $this->settlement->settleIfPaid($locked, $userId);

            return $payment;
        });
    }
}
