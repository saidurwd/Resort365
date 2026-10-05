<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use App\Support\Cash\CashCount;
use Brick\Math\BigDecimal;
use Modules\Billing\Enums\CashierShiftStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Models\CashierShift;
use Modules\Billing\Models\Payment;

/**
 * Closes a shift with the cash counted by denomination (ARCHITECTURE §5.9): the cash received and
 * refunded in the shift give the expected cash, the count gives the variance, which needs a reason
 * when it is not zero. The closed shift is locked; later payments are no longer added to it.
 */
class CloseShift extends Action
{
    public function __construct(
        private readonly CashCount $count,
    ) {}

    /**
     * @param  array<int|string, int|string|null>  $denominations  denomination => how many
     *
     * @throws PaymentNotAllowed
     */
    public function handle(CashierShift $shift, array $denominations, ?string $reason, int $userId): CashierShift
    {
        return $this->transaction(function () use ($shift, $denominations, $reason, $userId): CashierShift {
            $locked = CashierShift::query()->lockForUpdate()->findOrFail($shift->id);

            if (! $locked->isOpen()) {
                throw new PaymentNotAllowed(__('This shift is already closed.'));
            }

            [$received, $refunded] = $this->cash($locked);
            $counted = $this->count->counted($denominations);
            $expected = $this->count->expected($locked->opening_float, $received, $refunded);
            $variance = $this->count->variance($counted, $expected);
            $reason = trim((string) $reason);

            if (! BigDecimal::of($variance)->isZero() && $reason === '') {
                throw new PaymentNotAllowed(BigDecimal::of($variance)->isNegative()
                    ? __('The cash is :amount short: give a reason.', ['amount' => (string) BigDecimal::of($variance)->abs()])
                    : __('The cash is :amount over: give a reason.', ['amount' => $variance]));
            }

            $locked->forceFill([
                'closed_at' => now(),
                'closed_by' => $userId,
                'cash_received' => $received,
                'cash_refunded' => $refunded,
                'expected_cash' => $expected,
                'counted_cash' => $counted,
                'cash_variance' => $variance,
                'variance_reason' => $reason !== '' ? $reason : null,
                'denominations' => array_filter(array_map(fn (int|string|null $count): int => (int) $count, $denominations)),
                'status' => CashierShiftStatus::Closed,
                'open_user_id' => null,
            ])->save();

            return $locked;
        }, attempts: 3);
    }

    /**
     * Cash taken and cash paid back in the shift.
     *
     * @return array{string, string}
     */
    public function cash(CashierShift $shift): array
    {
        $payments = Payment::query()->where('cashier_shift_id', $shift->id)->where('method', PaymentMethod::Cash->value)
            ->where('status', PaymentStatus::Succeeded->value)->get(['payment_type', 'amount']);
        $sum = fn (bool $refunds): string => (string) $payments->filter(fn (Payment $payment): bool => ($payment->payment_type === PaymentType::Refund) === $refunds)
            ->reduce(fn (BigDecimal $total, Payment $payment): BigDecimal => $total->plus($payment->amount), BigDecimal::zero())->toScale(2);

        return [$sum(false), $sum(true)];
    }
}
