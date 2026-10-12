<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\Contracts\TaxEngine;
use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Inventory\Contracts\StoreDirectory;
use Modules\Inventory\DTOs\StoreSummary;
use Modules\Procurement\DTOs\NewPurchaseOrder;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Enums\PurchaseRequisitionStatus;
use Modules\Procurement\Enums\VendorStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseOrderLine;
use Modules\Procurement\Models\PurchaseRequisition;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Services\PurchaseTotals;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\DTOs\DepartmentSummary;

/**
 * Writes a purchase order or changes a draft one (Step 5.5): vendor, receiving store, lines with prices and tax, and the
 * totals. Ordering from an approved requisition marks it ordered. Only a draft can be changed.
 */
class SavePurchaseOrder extends Action
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly TaxEngine $taxes,
        private readonly ItemCatalog $items,
        private readonly StoreDirectory $stores,
        private readonly DepartmentDirectory $departments,
        private readonly PurchaseTotals $totals,
    ) {}

    /**
     * @throws ProcurementRuleViolated
     */
    public function handle(?PurchaseOrder $order, NewPurchaseOrder $data): PurchaseOrder
    {
        $vendor = Vendor::query()->find($data->vendorId) ?? throw new ProcurementRuleViolated(__('Choose a vendor.'));

        if ($vendor->status !== VendorStatus::Active) {
            throw new ProcurementRuleViolated(__(':vendor is not active.', ['vendor' => $vendor->name]));
        }

        $store = $this->stores->find($data->storeId);

        if (! $store instanceof StoreSummary || $store->propertyId !== $data->propertyId) {
            throw new ProcurementRuleViolated(__('Choose the store that receives the goods.'));
        }

        if ($data->departmentId !== null && ! $this->departments->find($data->departmentId) instanceof DepartmentSummary) {
            throw new ProcurementRuleViolated(__('Choose a department of this company.'));
        }

        if ($data->lines === []) {
            throw new ProcurementRuleViolated(__('Add at least one item.'));
        }

        if (count(array_unique(array_column($data->lines, 'item_id'))) !== count($data->lines)) {
            throw new ProcurementRuleViolated(__('An item is listed once per order.'));
        }

        $known = $this->items->many(array_column($data->lines, 'item_id'));
        $rows = [];

        foreach ($data->lines as $line) {
            $item = $known[(int) $line['item_id']] ?? throw new ProcurementRuleViolated(__('Unknown item.'));

            if (! BigDecimal::of($line['quantity'])->isPositive() || ! BigDecimal::of($line['unit_price'])->isPositive()) {
                throw new ProcurementRuleViolated(__(':item needs a quantity and a price above zero.', ['item' => $item->name]));
            }

            $net = $this->totals->net($line['quantity'], $line['unit_price']);
            $categoryId = ($line['tax_category_id'] ?? null) ?: null;
            $tax = $this->taxes->calculate($net, $categoryId)->taxTotal;
            $rows[] = ['item_id' => $item->id, 'quantity' => $line['quantity'], 'unit_price' => $line['unit_price'], 'tax_category_id' => $categoryId, 'net_amount' => $net, 'tax_amount' => $tax, 'line_total' => (string) BigDecimal::of($net)->plus($tax)->toScale(2)];
        }

        $sum = $this->totals->summarise(array_map(fn (array $row): array => ['net' => $row['net_amount'], 'tax' => $row['tax_amount']], $rows));

        return $this->transaction(function () use ($order, $data, $vendor, $rows, $sum): PurchaseOrder {
            $values = [
                'vendor_id' => $vendor->id, 'store_id' => $data->storeId, 'department_id' => $data->departmentId, 'ordered_on' => $data->orderedOn ?? now()->toDateString(), 'expected_on' => $data->expectedOn,
                'payment_terms_days' => $data->paymentTermsDays ?? $vendor->payment_terms_days, 'currency' => $vendor->currency, 'subtotal' => $sum['subtotal'], 'tax_total' => $sum['tax_total'], 'total' => $sum['total'], 'notes' => $data->notes,
            ];

            if ($order instanceof PurchaseOrder) {
                $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

                if ($locked->status !== PurchaseOrderStatus::Draft) {
                    throw new ProcurementRuleViolated(__('Order :no is :status: only a draft can be changed.', ['no' => $locked->order_no, 'status' => mb_strtolower($locked->status->label())]));
                }

                $locked->forceFill($values)->save();
                PurchaseOrderLine::query()->where('purchase_order_id', $locked->id)->delete();
                $order = $locked;
            } else {
                if ($data->requisitionId !== null) {
                    $requisition = PurchaseRequisition::query()->lockForUpdate()->find($data->requisitionId);

                    if (! $requisition instanceof PurchaseRequisition || $requisition->status !== PurchaseRequisitionStatus::Approved) {
                        throw new ProcurementRuleViolated(__('Only an approved requisition can be ordered.'));
                    }

                    $requisition->forceFill(['status' => PurchaseRequisitionStatus::Ordered])->save();
                }

                $order = PurchaseOrder::query()->create([
                    'property_id' => $data->propertyId, 'order_no' => $this->numbers->next('purchase_order'), 'purchase_requisition_id' => $data->requisitionId, 'vendor_quotation_id' => $data->quotationId,
                    'status' => PurchaseOrderStatus::Draft, 'created_by' => $data->userId, ...$values,
                ]);
            }

            foreach ($rows as $row) {
                PurchaseOrderLine::query()->create(['property_id' => $order->property_id, 'purchase_order_id' => $order->id, ...$row]);
            }

            return $order->load('lines');
        }, attempts: 3);
    }
}
