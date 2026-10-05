<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Restaurant\Enums\DiscountType;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Services\DiscountAllocator;
use Modules\Restaurant\Services\DiscountLimits;
use Modules\Restaurant\Services\ManagerApprovals;
use Modules\Restaurant\Services\OrderBilling;

/**
 * Gives (or takes off) a discount on an item or on the whole bill before it is printed (ARCHITECTURE
 * §5.10.7): a percentage or an amount, always with a reason. Up to the person's limit (DiscountLimits)
 * it is theirs to give; above it a manager approves with their PIN (`bill.discount`, used once).
 */
class DiscountOrder extends Action
{
    public const string APPROVAL = 'bill.discount';

    public function __construct(
        private readonly DiscountAllocator $allocator,
        private readonly DiscountLimits $limits,
        private readonly ManagerApprovals $approvals,
        private readonly OrderBilling $billing,
    ) {}

    /**
     * @param  PosOrderLine|null  $line  null for a discount on the whole bill
     * @param  DiscountType|null  $type  null takes the discount off
     *
     * @throws PosNotAllowed
     */
    public function handle(PosOrder $order, ?PosOrderLine $line, ?DiscountType $type, ?string $value, ?string $reason, int $userId, ?int $approvalId = null): void
    {
        $this->transaction(function () use ($order, $line, $type, $value, $reason, $userId, $approvalId): void {
            $locked = PosOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! $locked->isOpen()) {
                throw new PosNotAllowed(__('The bill is printed: reopen it to change discounts.'));
            }

            if ($line instanceof PosOrderLine && ($line->pos_order_id !== $locked->id || $line->status === OrderLineStatus::Voided)) {
                throw new PosNotAllowed(__('This item cannot be discounted.'));
            }

            $target = $line ?? $locked;

            if (! $type instanceof DiscountType || $value === null || BigDecimal::of($value)->isZero()) {
                $target->forceFill(['discount_type' => null, 'discount_value' => null, 'discount_reason' => null, 'discount_by' => null, 'discount_approval_id' => null])->save();

                return;
            }

            if (trim((string) $reason) === '') {
                throw new PosNotAllowed(__('Say why the discount is given.'));
            }

            if ($type === DiscountType::Percent && BigDecimal::of($value)->isGreaterThan(100)) {
                throw new PosNotAllowed(__('A discount cannot be more than 100%.'));
            }

            $base = $this->base($locked, $line);
            $cents = $this->allocator->amount($base, $type, $value);
            $percent = $this->allocator->percentOf($cents, $base);
            $approval = null;

            if (BigDecimal::of($percent)->isGreaterThan($this->limits->maxPercent($userId))) {
                $approval = $approvalId !== null
                    ? $this->approvals->consume($approvalId, self::APPROVAL, $userId, 'pos_order', $locked->id)
                    : throw PosNotAllowed::needsApproval(__('A :percent% discount is above your limit of :limit%: a manager must approve it.', [
                        'percent' => $percent, 'limit' => $this->limits->maxPercent($userId),
                    ]), self::APPROVAL);
            }

            $target->forceFill([
                'discount_type' => $type, 'discount_value' => (string) BigDecimal::of($value)->toScale(2, RoundingMode::HalfUp), 'discount_reason' => mb_substr(trim((string) $reason), 0, 300),
                'discount_by' => $userId, 'discount_approval_id' => $approval?->id,
            ])->save();
        });
    }

    /**
     * What the discount is taken off, in cents: the item's line; for the whole bill, the lines after their own discounts.
     */
    private function base(PosOrder $order, ?PosOrderLine $line): int
    {
        if ($line instanceof PosOrderLine) {
            return BigDecimal::of($line->line_total)->multipliedBy(100)->toInt();
        }

        $data = $this->billing->lines($order);

        return $data['base'] - $data['itemDiscount'];
    }
}
