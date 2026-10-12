<?php

namespace Modules\Procurement\Services;

use Brick\Math\BigDecimal;
use Modules\Procurement\Contracts\ProcurementFacts;
use Modules\Procurement\DTOs\ReceiptFact;
use Modules\Procurement\DTOs\ReturnFact;
use Modules\Procurement\DTOs\VendorBillFact;
use Modules\Procurement\DTOs\VendorPaymentFact;
use Modules\Procurement\Enums\VendorBillKind;
use Modules\Procurement\Enums\VendorBillStatus;
use Modules\Procurement\Models\GoodsReceipt;
use Modules\Procurement\Models\PurchaseReturn;
use Modules\Procurement\Models\VendorBill;
use Modules\Procurement\Models\VendorPayment;

class ProcurementFactsService implements ProcurementFacts
{
    public function __construct(private readonly ItemLabels $labels) {}

    public function receipt(int $receiptId): ?ReceiptFact
    {
        $receipt = GoodsReceipt::query()->with('lines')->find($receiptId);

        if (! $receipt instanceof GoodsReceipt) {
            return null;
        }

        $items = $this->labels->for($receipt->lines->pluck('item_id'));
        $byClass = [];

        foreach ($receipt->lines as $line) {
            $class = $items[$line->item_id]->accountClass ?? 'operating';
            $byClass[$class] = (string) BigDecimal::of($byClass[$class] ?? '0')->plus($line->value)->toScale(2);
        }

        return new ReceiptFact($receipt->id, $receipt->receipt_no, $receipt->property_id, $receipt->vendor_id, $receipt->received_on->toDateString(), $byClass);
    }

    public function bill(int $billId): ?VendorBillFact
    {
        $bill = VendorBill::query()->with('lines')->find($billId);

        if (! $bill instanceof VendorBill || in_array($bill->status, [VendorBillStatus::Draft, VendorBillStatus::Pending, VendorBillStatus::Cancelled], true)) {
            return null;
        }

        $grni = BigDecimal::zero();
        $expense = [];

        foreach ($bill->lines as $line) {
            $grni = $grni->plus($line->grni_amount);

            if ($bill->kind === VendorBillKind::Service) {
                $class = $line->expense_class->value ?? 'other';
                $expense[$class] = (string) BigDecimal::of($expense[$class] ?? '0')->plus($line->net_amount)->toScale(2);
            }
        }

        $variance = $bill->kind === VendorBillKind::Purchase ? BigDecimal::of($bill->subtotal)->minus($grni) : BigDecimal::zero();

        return new VendorBillFact($bill->id, $bill->bill_no, $bill->property_id, $bill->vendor_id, $bill->department_id, $bill->bill_date->toDateString(),
            (string) $grni->toScale(2), (string) $variance->toScale(2), $expense, (string) BigDecimal::of($bill->tax_total)->toScale(2), (string) BigDecimal::of($bill->total)->toScale(2));
    }

    public function payment(int $paymentId): ?VendorPaymentFact
    {
        $payment = VendorPayment::query()->find($paymentId);

        return $payment instanceof VendorPayment ? new VendorPaymentFact($payment->id, $payment->payment_no, $payment->property_id, $payment->vendor_id, $payment->paid_on->toDateString(), $payment->method->value, (string) $payment->amount) : null;
    }

    public function purchaseReturn(int $returnId): ?ReturnFact
    {
        $return = PurchaseReturn::query()->with('lines')->find($returnId);

        if (! $return instanceof PurchaseReturn) {
            return null;
        }

        $items = $this->labels->for($return->lines->pluck('item_id'));
        $grni = BigDecimal::zero();
        $billed = BigDecimal::zero();
        $tax = BigDecimal::zero();
        $stock = BigDecimal::zero();
        $value = BigDecimal::zero();
        $byClass = [];

        foreach ($return->lines as $line) {
            $grni = $grni->plus($line->grni_value);
            $billed = $billed->plus($line->billed_value);
            $tax = $tax->plus($line->tax_value);
            $value = $value->plus($line->value);
            $stock = $stock->plus($line->stock_cost);
            $class = $items[$line->item_id]->accountClass ?? 'operating';
            $byClass[$class] = (string) BigDecimal::of($byClass[$class] ?? '0')->plus($line->stock_cost)->toScale(2);
        }

        return new ReturnFact($return->id, $return->debit_note_no, $return->property_id, $return->vendor_id, $return->returned_on->toDateString(), (string) $grni->toScale(2), (string) $billed->toScale(2), (string) $tax->toScale(2), $byClass, (string) $value->minus($stock)->toScale(2));
    }

    public function history(): array
    {
        $ids = fn ($query): array => $query->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return [
            'receipts' => $ids(GoodsReceipt::query()),
            'bills' => $ids(VendorBill::query()->whereIn('status', [VendorBillStatus::Approved->value, VendorBillStatus::Partial->value, VendorBillStatus::Paid->value])),
            'payments' => $ids(VendorPayment::query()),
            'returns' => $ids(PurchaseReturn::query()),
        ];
    }
}
