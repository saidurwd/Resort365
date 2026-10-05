<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Enums\OrderStatus;
use Modules\Restaurant\Enums\OrderType;
use Modules\Restaurant\Enums\TableStatus;
use Modules\Restaurant\Events\TableStatusChanged;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Services\PosNumbers;

/**
 * Starts an order (ARCHITECTURE §5.10.5): dine-in at a free table of the outlet, with the number of
 * covers (the table becomes occupied), or takeaway. Numbered per outlet and business date.
 */
class OpenOrder extends Action
{
    public function __construct(
        private readonly PosNumbers $numbers,
        private readonly PropertyDirectory $properties,
    ) {}

    /**
     * @throws PosNotAllowed
     */
    public function handle(Outlet $outlet, OrderType $type, int $waiterId, ?int $tableId = null, int $covers = 1): PosOrder
    {
        return $this->transaction(function () use ($outlet, $type, $waiterId, $tableId, $covers): PosOrder {
            $table = null;

            if ($type === OrderType::DineIn) {
                $table = DiningTable::query()->where('outlet_id', $outlet->id)->where('is_active', true)->lockForUpdate()->find($tableId);

                if (! $table instanceof DiningTable) {
                    throw new PosNotAllowed(__('Choose a table of this outlet.'));
                }

                if (PosOrder::query()->where('dining_table_id', $table->id)->where('status', OrderStatus::Open->value)->exists()) {
                    throw new PosNotAllowed(__('Table :number already has an open order.', ['number' => $table->number]));
                }
            }

            $date = $this->properties->find($outlet->property_id)->businessDate ?? now()->toDateString();
            $order = PosOrder::query()->create([
                'property_id' => $outlet->property_id, 'outlet_id' => $outlet->id, 'order_no' => $this->numbers->nextOrderNo($outlet, $date), 'business_date' => $date,
                'order_type' => $type, 'dining_table_id' => $table?->id, 'covers' => max(1, $covers), 'waiter_id' => $waiterId, 'status' => OrderStatus::Open,
                'subtotal' => '0.00', 'opened_at' => now(),
            ]);

            $table?->forceFill(['status' => TableStatus::Occupied])->save();
            TableStatusChanged::dispatch($order->tenant_id, $outlet->id, $table instanceof DiningTable ? [$table->id] : []);

            return $order;
        });
    }
}
