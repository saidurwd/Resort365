<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\Contracts\Settings;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Events\GoodsReceived;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\GoodsReceipt;
use Modules\Procurement\Models\GoodsReceiptLine;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseOrderLine;
use Modules\Procurement\Services\OrderProgress;

/**
 * Receives goods against a purchase order (Step 5.6), in full or in part. Accepted quantities go into the order's store
 * at the order's price (before tax); rejected ones are recorded with a reason and do not count. The order becomes
 * partly received, then received. Receiving more than ordered is allowed up to `procurement.over_receipt_percent`.
 */
class ReceiveGoods extends Action
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly StockMovementContract $stock,
        private readonly Settings $settings,
        private readonly OrderProgress $progress,
    ) {}

    /**
     * @param  list<array{purchase_order_line_id: int, accepted_quantity: string, rejected_quantity?: string|null, reject_reason?: string|null}>  $lines
     *
     * @throws ProcurementRuleViolated
     */
    public function handle(PurchaseOrder $order, array $lines, int $userId, ?string $receivedOn = null, ?string $deliveryNote = null, ?string $notes = null): GoodsReceipt
    {
        return $this->transaction(function () use ($order, $lines, $userId, $receivedOn, $deliveryNote, $notes): GoodsReceipt {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($locked->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Sent, PurchaseOrderStatus::Partial], true)) {
                throw new ProcurementRuleViolated(__('Order :no is :status: goods are received against an approved order that is not yet complete.', ['no' => $locked->order_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            $over = (string) $this->settings->get('procurement.over_receipt_percent', $locked->property_id);
            $orderLines = PurchaseOrderLine::query()->where('purchase_order_id', $locked->id)->lockForUpdate()->get()->keyBy('id');
            $rows = [];

            foreach ($lines as $line) {
                $accepted = BigDecimal::of($line['accepted_quantity'] !== '' ? $line['accepted_quantity'] : '0');
                $rejected = BigDecimal::of(($line['rejected_quantity'] ?? '') !== '' ? ($line['rejected_quantity'] ?? '0') : '0');

                if ($accepted->isZero() && $rejected->isZero()) {
                    continue;
                }

                $orderLine = $orderLines->get((int) $line['purchase_order_line_id']) ?? throw new ProcurementRuleViolated(__('That line is not on this order.'));

                if ($accepted->isNegative() || $rejected->isNegative()) {
                    throw new ProcurementRuleViolated(__('A quantity cannot be negative.'));
                }

                if ($rejected->isPositive() && trim((string) ($line['reject_reason'] ?? '')) === '') {
                    throw new ProcurementRuleViolated(__('Give a reason for the rejected quantity.'));
                }

                $limit = BigDecimal::of($orderLine->quantity)->multipliedBy(BigDecimal::of('100')->plus($over))->dividedBy('100', 4, RoundingMode::Down);

                if (BigDecimal::of($orderLine->received_quantity)->plus($accepted)->isGreaterThan($limit)) {
                    throw new ProcurementRuleViolated(__('Order :no asked for :ordered of this item and :received is already in: you can receive at most :limit.', [
                        'no' => $locked->order_no, 'ordered' => rtrim(rtrim((string) $orderLine->quantity, '0'), '.'), 'received' => rtrim(rtrim((string) $orderLine->received_quantity, '0'), '.') ?: '0', 'limit' => rtrim(rtrim((string) $limit, '0'), '.'),
                    ]));
                }

                $rows[] = ['line' => $orderLine, 'accepted' => $accepted, 'rejected' => $rejected, 'reason' => trim((string) ($line['reject_reason'] ?? '')) ?: null];
            }

            if ($rows === []) {
                throw new ProcurementRuleViolated(__('Enter what was received.'));
            }

            $receipt = GoodsReceipt::query()->create([
                'property_id' => $locked->property_id, 'receipt_no' => $this->numbers->next('goods_receipt'), 'purchase_order_id' => $locked->id, 'vendor_id' => $locked->vendor_id, 'store_id' => $locked->store_id,
                'received_on' => $receivedOn ?? now()->toDateString(), 'delivery_note' => $deliveryNote, 'notes' => $notes, 'received_by' => $userId, 'total_value' => '0',
            ]);
            $total = BigDecimal::zero();

            foreach ($rows as $row) {
                /** @var PurchaseOrderLine $orderLine */
                $orderLine = $row['line'];
                $value = $row['accepted']->multipliedBy($orderLine->unit_price)->toScale(2, RoundingMode::HalfUp);
                $movementId = null;

                if ($row['accepted']->isPositive()) {
                    try {
                        $movementId = $this->stock->record(new NewStockMovement(
                            MovementType::Receipt, $locked->store_id, $orderLine->item_id, (string) $row['accepted'], (string) $orderLine->unit_price, date: $receipt->received_on->toDateString(), reason: 'purchase', notes: $receipt->receipt_no,
                            sourceType: 'goods_receipt', sourceId: $receipt->id, sourceEvent: 'received', idempotencyKey: 'grn:'.$receipt->id.':'.$orderLine->id, userId: $userId,
                        ))->id;
                    } catch (StockRuleViolated $exception) {
                        throw new ProcurementRuleViolated($exception->getMessage(), $exception->getCode(), $exception);
                    }
                }

                GoodsReceiptLine::query()->create([
                    'property_id' => $locked->property_id, 'goods_receipt_id' => $receipt->id, 'purchase_order_line_id' => $orderLine->id, 'item_id' => $orderLine->item_id, 'accepted_quantity' => (string) $row['accepted'],
                    'rejected_quantity' => (string) $row['rejected'], 'reject_reason' => $row['reason'], 'unit_cost' => (string) $orderLine->unit_price, 'value' => (string) $value, 'stock_movement_id' => $movementId,
                ]);
                $orderLine->forceFill(['received_quantity' => (string) BigDecimal::of($orderLine->received_quantity)->plus($row['accepted'])->toScale(4)])->save();
                $total = $total->plus($value);
            }

            $receipt->forceFill(['total_value' => (string) $total])->save();
            $this->progress->refresh($locked);

            if ($total->isPositive()) {
                GoodsReceived::dispatch($receipt->tenant_id, $receipt->property_id, $receipt->id);
            }

            return $receipt->load('lines');
        }, attempts: 3);
    }
}
