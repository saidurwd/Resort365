<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Procurement\Enums\VendorBillStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\VendorBill;
use Modules\Procurement\Models\VendorPayment;
use Modules\Procurement\Models\VendorPaymentAllocation;
use Modules\Procurement\Services\OrderProgress;

/**
 * Applies a payment (or what is left of an advance) to approved bills of the same vendor (Step 5.6). A bill takes at
 * most its balance and the payment at most what it still has. The ledger is not touched: the payment already cleared
 * accounts payable when it was made.
 */
class AllocateVendorPayment extends Action
{
    public function __construct(private readonly OrderProgress $progress) {}

    /**
     * @param  array<int, string>  $allocations  bill id => amount
     *
     * @throws ProcurementRuleViolated
     */
    public function handle(VendorPayment $payment, array $allocations): VendorPayment
    {
        return $this->transaction(function () use ($payment, $allocations): VendorPayment {
            $locked = VendorPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $left = BigDecimal::of($locked->amount)->minus($locked->allocated_amount);
            $applied = BigDecimal::zero();

            foreach ($allocations as $billId => $amount) {
                $amount = BigDecimal::of($amount !== '' ? $amount : '0');

                if ($amount->isZero()) {
                    continue;
                }

                if ($amount->isNegative()) {
                    throw new ProcurementRuleViolated(__('An amount cannot be negative.'));
                }

                $bill = VendorBill::query()->lockForUpdate()->find((int) $billId);

                if (! $bill instanceof VendorBill || $bill->vendor_id !== $locked->vendor_id || ! in_array($bill->status, [VendorBillStatus::Approved, VendorBillStatus::Partial], true)) {
                    throw new ProcurementRuleViolated(__('Pay approved bills of this vendor only.'));
                }

                $balance = BigDecimal::of($bill->total)->minus($bill->paid_amount);

                if ($amount->isGreaterThan($balance)) {
                    throw new ProcurementRuleViolated(__('Bill :no has only :balance left to pay.', ['no' => $bill->bill_no, 'balance' => number_format((float) (string) $balance, 2)]));
                }

                VendorPaymentAllocation::query()->create(['property_id' => $locked->property_id, 'vendor_payment_id' => $locked->id, 'vendor_bill_id' => $bill->id, 'amount' => (string) $amount->toScale(2)]);
                $paid = BigDecimal::of($bill->paid_amount)->plus($amount);
                $bill->forceFill(['paid_amount' => (string) $paid->toScale(2), 'status' => $paid->isEqualTo($bill->total) ? VendorBillStatus::Paid : VendorBillStatus::Partial])->save();
                $applied = $applied->plus($amount);

                $order = $bill->purchase_order_id !== null ? PurchaseOrder::query()->find($bill->purchase_order_id) : null;

                if ($order instanceof PurchaseOrder) {
                    $this->progress->refresh($order);
                }
            }

            if ($applied->isGreaterThan($left)) {
                throw new ProcurementRuleViolated(__('Payment :no has only :left left to allocate.', ['no' => $locked->payment_no, 'left' => number_format((float) (string) $left, 2)]));
            }

            $locked->forceFill(['allocated_amount' => (string) BigDecimal::of($locked->allocated_amount)->plus($applied)->toScale(2)])->save();

            return $locked;
        });
    }
}
