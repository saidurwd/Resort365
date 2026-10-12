<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\Contracts\Settings;
use Modules\Core\Contracts\TaxEngine;
use Modules\Procurement\DTOs\NewVendorBill;
use Modules\Procurement\Enums\ExpenseClass;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Enums\VendorBillKind;
use Modules\Procurement\Enums\VendorBillStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseOrderLine;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorBill;
use Modules\Procurement\Models\VendorBillLine;
use Modules\Procurement\Services\OrderQuantities;
use Modules\Procurement\Services\PurchaseTotals;
use Modules\Procurement\Services\ThreeWayMatch;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\DTOs\DepartmentSummary;

/**
 * Enters a vendor bill or changes a draft one (Step 5.6). A goods bill is matched three ways against the order and what
 * was received: billing more than was received (less returns and earlier bills) is refused, and a price above the order's
 * beyond the tolerance needs an override. A service bill has no order. Only a draft can be changed.
 */
class SaveVendorBill extends Action
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly TaxEngine $taxes,
        private readonly Settings $settings,
        private readonly ThreeWayMatch $match,
        private readonly OrderQuantities $quantities,
        private readonly PurchaseTotals $totals,
        private readonly DepartmentDirectory $departments,
    ) {}

    /**
     * @throws ProcurementRuleViolated
     */
    public function handle(?VendorBill $bill, NewVendorBill $data): VendorBill
    {
        $vendor = Vendor::query()->find($data->vendorId) ?? throw new ProcurementRuleViolated(__('Choose a vendor.'));

        if ($data->departmentId !== null && ! $this->departments->find($data->departmentId) instanceof DepartmentSummary) {
            throw new ProcurementRuleViolated(__('Choose a department of this company.'));
        }

        if ($data->lines === []) {
            throw new ProcurementRuleViolated(__('Add at least one line.'));
        }

        return $this->transaction(function () use ($bill, $data, $vendor): VendorBill {
            if ($bill instanceof VendorBill) {
                $bill = VendorBill::query()->lockForUpdate()->findOrFail($bill->id);

                if ($bill->status !== VendorBillStatus::Draft) {
                    throw new ProcurementRuleViolated(__('Bill :no is :status: only a draft can be changed.', ['no' => $bill->bill_no, 'status' => mb_strtolower($bill->status->label())]));
                }
            }

            $order = null;

            if ($data->kind === VendorBillKind::Purchase) {
                $order = PurchaseOrder::query()->lockForUpdate()->find($data->orderId) ?? throw new ProcurementRuleViolated(__('Choose the order this bill is for.'));

                if ($order->vendor_id !== $vendor->id) {
                    throw new ProcurementRuleViolated(__('That order is from another vendor.'));
                }

                if (! in_array($order->status, [PurchaseOrderStatus::Partial, PurchaseOrderStatus::Received, PurchaseOrderStatus::Closed], true)) {
                    throw new ProcurementRuleViolated(__('Order :no has received nothing yet: bill what has been received.', ['no' => $order->order_no]));
                }
            }

            [$rows, $overridden] = $order instanceof PurchaseOrder ? $this->purchaseLines($order, $data, $bill) : [$this->serviceLines($data), false];
            $sum = $this->totals->summarise(array_map(fn (array $row): array => ['net' => $row['net_amount'], 'tax' => $row['tax_amount']], $rows));
            $billDate = $data->billDate ?? now()->toDateString();
            $values = [
                'vendor_reference' => $data->vendorReference, 'vendor_id' => $vendor->id, 'purchase_order_id' => $order?->id, 'kind' => $data->kind, 'bill_date' => $billDate,
                'due_on' => $data->dueOn ?? CarbonImmutable::parse($billDate)->addDays($order->payment_terms_days ?? $vendor->payment_terms_days)->toDateString(), 'currency' => $vendor->currency,
                'department_id' => $data->departmentId, 'subtotal' => $sum['subtotal'], 'tax_total' => $sum['tax_total'], 'total' => $sum['total'], 'match_overridden' => $overridden,
                'match_notes' => $overridden ? __('Price above the order price, approved by override.') : null, 'notes' => $data->notes,
            ];

            if ($bill instanceof VendorBill) {
                $bill->forceFill($values)->save();
                VendorBillLine::query()->where('vendor_bill_id', $bill->id)->delete();
            } else {
                $bill = VendorBill::query()->create(['property_id' => $data->propertyId, 'bill_no' => $this->numbers->next('vendor_bill'), 'status' => VendorBillStatus::Draft, 'paid_amount' => '0', 'created_by' => $data->userId, ...$values]);
            }

            foreach ($rows as $row) {
                VendorBillLine::query()->create(['property_id' => $bill->property_id, 'vendor_bill_id' => $bill->id, ...$row]);
            }

            return $bill->load('lines');
        }, attempts: 3);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: bool} the lines and whether a price needed the override
     *
     * @throws ProcurementRuleViolated
     */
    private function purchaseLines(PurchaseOrder $order, NewVendorBill $data, ?VendorBill $bill): array
    {
        $orderLines = PurchaseOrderLine::query()->where('purchase_order_id', $order->id)->get()->keyBy('id');
        $checks = [];
        $rows = [];

        foreach ($data->lines as $line) {
            $orderLine = $orderLines->get((int) ($line['purchase_order_line_id'] ?? 0)) ?? throw new ProcurementRuleViolated(__('That line is not on this order.'));
            $quantity = (string) ($line['quantity'] ?? '0');
            $price = (string) ($line['unit_price'] ?? '0');

            if (! BigDecimal::of($quantity)->isPositive() || ! BigDecimal::of($price)->isPositive()) {
                throw new ProcurementRuleViolated(__('Every bill line needs a quantity and a price above zero.'));
            }

            if (isset($checks[$orderLine->id])) {
                throw new ProcurementRuleViolated(__('An order line is billed once per bill.'));
            }

            $checks[$orderLine->id] = ['billed' => $quantity, 'price' => $price, 'received' => (string) $orderLine->received_quantity, 'returned' => $this->quantities->returned($orderLine->id),
                'already_billed' => $this->quantities->billed($orderLine->id, $bill?->id), 'po_price' => (string) $orderLine->unit_price];
            $net = $this->totals->net($quantity, $price);
            $tax = $this->taxes->calculate($net, $orderLine->tax_category_id)->taxTotal;
            $rows[$orderLine->id] = [
                'purchase_order_line_id' => $orderLine->id, 'item_id' => $orderLine->item_id, 'quantity' => $quantity, 'unit_price' => $price, 'tax_category_id' => $orderLine->tax_category_id,
                'net_amount' => $net, 'tax_amount' => $tax, 'line_total' => (string) BigDecimal::of($net)->plus($tax)->toScale(2), 'grni_amount' => $this->totals->net($quantity, (string) $orderLine->unit_price),
            ];
        }

        $result = $this->match->check($checks, (string) $this->settings->get('procurement.quantity_tolerance_percent', $order->property_id), (string) $this->settings->get('procurement.price_tolerance_percent', $order->property_id));

        foreach ($result['lines'] as $orderLineId => $check) {
            if (! $check['quantity_ok']) {
                throw new ProcurementRuleViolated(__('3-way match: you are billing :billed but only :allowed of this item can still be billed (received less returned and already billed) on order :no.', [
                    'billed' => rtrim(rtrim($checks[$orderLineId]['billed'], '0'), '.'), 'allowed' => rtrim(rtrim($check['allowed'], '0'), '.') ?: '0', 'no' => $order->order_no,
                ]));
            }
        }

        if ($result['price_flagged'] && ! $data->overrideMatch) {
            throw new ProcurementRuleViolated(__('3-way match: a billed price is above the order price by more than the tolerance. Correct the price, or ask someone who may override the match.'));
        }

        return [array_values($rows), $result['price_flagged']];
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ProcurementRuleViolated
     */
    private function serviceLines(NewVendorBill $data): array
    {
        $rows = [];

        foreach ($data->lines as $line) {
            $description = trim((string) ($line['description'] ?? ''));
            $quantity = (string) (($line['quantity'] ?? '') !== '' ? $line['quantity'] : '1');
            $price = (string) ($line['unit_price'] ?? '0');

            if ($description === '' || ! BigDecimal::of($quantity)->isPositive() || ! BigDecimal::of($price)->isPositive()) {
                throw new ProcurementRuleViolated(__('Every service line needs a description, a quantity and a price above zero.'));
            }

            $net = $this->totals->net($quantity, $price);
            $categoryId = ($line['tax_category_id'] ?? null) ?: null;
            $tax = $this->taxes->calculate($net, $categoryId)->taxTotal;
            $rows[] = [
                'purchase_order_line_id' => null, 'item_id' => null, 'description' => $description, 'expense_class' => ExpenseClass::tryFrom((string) ($line['expense_class'] ?? '')) ?? ExpenseClass::Other, 'quantity' => $quantity,
                'unit_price' => $price, 'tax_category_id' => $categoryId, 'net_amount' => $net, 'tax_amount' => $tax, 'line_total' => (string) BigDecimal::of($net)->plus($tax)->toScale(2), 'grni_amount' => '0',
            ];
        }

        return $rows;
    }
}
