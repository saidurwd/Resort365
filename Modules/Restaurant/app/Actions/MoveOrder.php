<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Enums\OrderStatus;
use Modules\Restaurant\Enums\OrderType;
use Modules\Restaurant\Enums\TableStatus;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Services\OrderTotals;

/**
 * Table operations (ARCHITECTURE §5.10.3): transfer an order to a free table of the outlet; merge another
 * open order of the outlet into this one (its lines move here, it is closed as merged and its table is
 * freed); cancel an order while nothing has gone to the kitchen (its table is freed).
 */
class MoveOrder extends Action
{
    public function __construct(
        private readonly OrderTotals $totals,
    ) {}

    /**
     * @throws PosNotAllowed
     */
    public function transfer(PosOrder $order, int $tableId): PosOrder
    {
        return $this->transaction(function () use ($order, $tableId): PosOrder {
            $locked = $this->open($order);
            $table = DiningTable::query()->where('outlet_id', $locked->outlet_id)->where('is_active', true)->lockForUpdate()->find($tableId);

            if (! $table instanceof DiningTable || $table->id === $locked->dining_table_id) {
                throw new PosNotAllowed(__('Choose another table of this outlet.'));
            }

            if (PosOrder::query()->where('dining_table_id', $table->id)->where('status', OrderStatus::Open->value)->exists()) {
                throw new PosNotAllowed(__('Table :number has an open order: merge them instead.', ['number' => $table->number]));
            }

            $this->free($locked->dining_table_id);
            $locked->forceFill(['dining_table_id' => $table->id, 'order_type' => OrderType::DineIn])->save();
            $table->forceFill(['status' => TableStatus::Occupied])->save();

            return $locked;
        });
    }

    /**
     * @throws PosNotAllowed
     */
    public function merge(PosOrder $order, int $otherOrderId): PosOrder
    {
        return $this->transaction(function () use ($order, $otherOrderId): PosOrder {
            $locked = $this->open($order);
            $other = PosOrder::query()->where('outlet_id', $locked->outlet_id)->where('status', OrderStatus::Open->value)->lockForUpdate()->find($otherOrderId);

            if (! $other instanceof PosOrder || $other->id === $locked->id) {
                throw new PosNotAllowed(__('Choose another open order of this outlet.'));
            }

            PosOrderLine::query()->where('pos_order_id', $other->id)->update(['pos_order_id' => $locked->id]);
            $other->kots()->update(['pos_order_id' => $locked->id]);
            $other->forceFill(['status' => OrderStatus::Cancelled, 'merged_into_id' => $locked->id, 'closed_at' => now(), 'subtotal' => '0.00'])->save();
            $locked->forceFill(['covers' => $locked->covers + $other->covers])->save();
            $this->free($other->dining_table_id);
            $this->totals->refresh($locked);

            return $locked;
        });
    }

    /**
     * @throws PosNotAllowed
     */
    public function cancel(PosOrder $order): PosOrder
    {
        return $this->transaction(function () use ($order): PosOrder {
            $locked = $this->open($order);

            if (PosOrderLine::query()->where('pos_order_id', $locked->id)->where('status', '!=', OrderLineStatus::Pending->value)->exists()) {
                throw new PosNotAllowed(__('Items went to the kitchen: void them, then settle the order.'));
            }

            PosOrderLine::query()->where('pos_order_id', $locked->id)->delete();
            $locked->forceFill(['status' => OrderStatus::Cancelled, 'closed_at' => now(), 'subtotal' => '0.00'])->save();
            $this->free($locked->dining_table_id);

            return $locked;
        });
    }

    /**
     * @throws PosNotAllowed
     */
    private function open(PosOrder $order): PosOrder
    {
        $locked = PosOrder::query()->lockForUpdate()->findOrFail($order->id);

        if (! $locked->isOpen()) {
            throw new PosNotAllowed(__('This order is closed.'));
        }

        return $locked;
    }

    private function free(?int $tableId): void
    {
        if ($tableId !== null) {
            DiningTable::query()->whereKey($tableId)->update(['status' => TableStatus::Available->value]);
        }
    }
}
