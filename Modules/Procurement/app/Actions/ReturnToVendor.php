<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Procurement\Events\PurchaseReturned;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\GoodsReceipt;
use Modules\Procurement\Models\GoodsReceiptLine;
use Modules\Procurement\Models\PurchaseOrderLine;
use Modules\Procurement\Models\PurchaseReturn;
use Modules\Procurement\Models\PurchaseReturnLine;
use Modules\Procurement\Services\OrderProgress;
use Modules\Procurement\Services\OrderQuantities;
use Modules\Procurement\Services\ReturnSplitter;

/**
 * Sends received goods back to the vendor with a debit note (Step 5.6). The stock leaves the receiving store, and the
 * value is split into the part never billed (it reverses the GRNI) and the part billed (it reduces the payable, with its
 * tax). A return cannot exceed what the receipt accepted and has not already sent back.
 */
class ReturnToVendor extends Action
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly StockMovementContract $stock,
        private readonly ReturnSplitter $splitter,
        private readonly OrderQuantities $quantities,
        private readonly OrderProgress $progress,
    ) {}

    /**
     * @param  list<array{goods_receipt_line_id: int, quantity: string}>  $lines
     *
     * @throws ProcurementRuleViolated
     */
    public function handle(GoodsReceipt $receipt, array $lines, string $reason, int $userId, ?string $returnedOn = null, ?string $notes = null): PurchaseReturn
    {
        if (trim($reason) === '') {
            throw new ProcurementRuleViolated(__('Give a reason for the return.'));
        }

        return $this->transaction(function () use ($receipt, $lines, $reason, $userId, $returnedOn, $notes): PurchaseReturn {
            $locked = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            $receiptLines = GoodsReceiptLine::query()->where('goods_receipt_id', $locked->id)->lockForUpdate()->get()->keyBy('id');
            $rows = [];

            foreach ($lines as $line) {
                $quantity = BigDecimal::of($line['quantity'] !== '' ? $line['quantity'] : '0');

                if ($quantity->isZero()) {
                    continue;
                }

                $receiptLine = $receiptLines->get((int) $line['goods_receipt_line_id']) ?? throw new ProcurementRuleViolated(__('That line is not on this receipt.'));
                $returnable = BigDecimal::of($receiptLine->accepted_quantity)->minus($receiptLine->returned_quantity);

                if ($quantity->isNegative() || $quantity->isGreaterThan($returnable)) {
                    throw new ProcurementRuleViolated(__('Only :left of this item can still be returned from receipt :no.', ['left' => rtrim(rtrim((string) $returnable, '0'), '.') ?: '0', 'no' => $locked->receipt_no]));
                }

                $rows[] = ['line' => $receiptLine, 'quantity' => $quantity];
            }

            if ($rows === []) {
                throw new ProcurementRuleViolated(__('Enter what is being returned.'));
            }

            $return = PurchaseReturn::query()->create([
                'property_id' => $locked->property_id, 'return_no' => $this->numbers->next('purchase_return'), 'debit_note_no' => $this->numbers->next('debit_note'), 'goods_receipt_id' => $locked->id,
                'vendor_id' => $locked->vendor_id, 'store_id' => $locked->store_id, 'returned_on' => $returnedOn ?? now()->toDateString(), 'reason' => trim($reason), 'notes' => $notes, 'total_value' => '0', 'tax_value' => '0', 'created_by' => $userId,
            ]);
            $total = BigDecimal::zero();
            $tax = BigDecimal::zero();

            foreach ($rows as $row) {
                /** @var GoodsReceiptLine $receiptLine */
                $receiptLine = $row['line'];
                $orderLine = PurchaseOrderLine::query()->findOrFail($receiptLine->purchase_order_line_id);
                $ratio = BigDecimal::of($orderLine->net_amount)->isPositive() ? (string) BigDecimal::of($orderLine->tax_amount)->dividedBy($orderLine->net_amount, 6, RoundingMode::HalfUp) : '0';
                $split = $this->splitter->split((string) $row['quantity'], (string) $orderLine->received_quantity, $this->quantities->returned($orderLine->id), $this->quantities->billed($orderLine->id), (string) $receiptLine->unit_cost, $ratio);

                try {
                    $movement = $this->stock->record(new NewStockMovement(
                        MovementType::ReturnToVendor, $locked->store_id, $receiptLine->item_id, (string) $row['quantity'], date: $return->returned_on->toDateString(), reason: 'return', notes: $return->debit_note_no,
                        sourceType: 'purchase_return', sourceId: $return->id, sourceEvent: 'returned', idempotencyKey: 'rtv:'.$return->id.':'.$receiptLine->id, userId: $userId,
                    ));
                } catch (StockRuleViolated $exception) {
                    throw new ProcurementRuleViolated($exception->getMessage(), $exception->getCode(), $exception);
                }

                PurchaseReturnLine::query()->create([
                    'property_id' => $locked->property_id, 'purchase_return_id' => $return->id, 'goods_receipt_line_id' => $receiptLine->id, 'item_id' => $receiptLine->item_id, 'quantity' => (string) $row['quantity'],
                    'unit_cost' => (string) $receiptLine->unit_cost, 'value' => $split['value'], 'grni_value' => $split['grni_value'], 'billed_value' => $split['billed_value'], 'tax_value' => $split['tax_value'],
                    'stock_cost' => (string) BigDecimal::of($movement->totalCost)->toScale(2, RoundingMode::HalfUp), 'stock_movement_id' => $movement->id,
                ]);
                $receiptLine->forceFill(['returned_quantity' => (string) BigDecimal::of($receiptLine->returned_quantity)->plus($row['quantity'])->toScale(4)])->save();
                $total = $total->plus($split['value']);
                $tax = $tax->plus($split['tax_value']);
            }

            $return->forceFill(['total_value' => (string) $total->toScale(2), 'tax_value' => (string) $tax->toScale(2)])->save();
            $order = $locked->order()->first();

            if ($order !== null) {
                $this->progress->refresh($order);
            }

            PurchaseReturned::dispatch($return->tenant_id, $return->property_id, $return->id);

            return $return->load('lines');
        }, attempts: 3);
    }
}
