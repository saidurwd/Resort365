<?php

namespace Modules\Restaurant\Services;

use Brick\Math\BigDecimal;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\PaymentMethod;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosPayment;
use Modules\Restaurant\Models\PosSession;

/**
 * The cash a POS session took and paid back, its takings by method, and its bills still open
 * (ARCHITECTURE §5.10.11). Cash in the drawer is each cash payment with its tip (the change went back
 * to the guest); refunds of voided bills come out of it.
 */
class SessionCash
{
    /**
     * @return array{string, string} cash received, cash refunded
     */
    public function cash(PosSession $session): array
    {
        $payments = PosPayment::query()->where('pos_session_id', $session->id)->where('method', PaymentMethod::Cash->value)->get(['amount', 'tip']);
        $received = BigDecimal::zero();
        $refunded = BigDecimal::zero();

        foreach ($payments as $payment) {
            $cash = BigDecimal::of($payment->amount)->plus($payment->tip);
            $cash->isNegative() ? $refunded = $refunded->minus($cash) : $received = $received->plus($cash);
        }

        return [(string) $received->toScale(2), (string) $refunded->toScale(2)];
    }

    /**
     * Net takings by method (payments less refunds, tips included).
     *
     * @return array<string, string> method value => amount
     */
    public function byMethod(PosSession $session): array
    {
        return PosPayment::query()->where('pos_session_id', $session->id)->get(['method', 'amount', 'tip'])
            ->groupBy(fn (PosPayment $payment): string => $payment->method->value)
            ->map(fn ($payments): string => (string) $payments->reduce(fn (BigDecimal $sum, PosPayment $payment): BigDecimal => $sum->plus($payment->amount)->plus($payment->tip), BigDecimal::zero())->toScale(2))
            ->all();
    }

    /**
     * Bills partly paid in this session and not yet settled: the session cannot close over them.
     */
    public function openBills(PosSession $session): int
    {
        return PosBill::query()->where('status', BillStatus::Printed->value)
            ->whereIn('id', PosPayment::query()->where('pos_session_id', $session->id)->select('pos_bill_id'))->count();
    }
}
