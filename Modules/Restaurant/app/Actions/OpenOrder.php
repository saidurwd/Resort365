<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Billing\Contracts\FolioPostingContract;
use Modules\Billing\DTOs\ChargeableStay;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\DTOs\OrderDestination;
use Modules\Restaurant\Enums\DeliveryStatus;
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
 * covers (the table becomes occupied), takeaway, room service to an in-house guest, a delivery to a place,
 * or a staff meal for a named person (OrderDestination). Deliveries start as ordered. Numbered per outlet
 * and business date.
 */
class OpenOrder extends Action
{
    public function __construct(
        private readonly PosNumbers $numbers,
        private readonly PropertyDirectory $properties,
        private readonly FolioPostingContract $folios,
    ) {}

    /**
     * @throws PosNotAllowed
     */
    public function handle(Outlet $outlet, OrderType $type, int $waiterId, ?int $tableId = null, int $covers = 1, ?OrderDestination $destination = null): PosOrder
    {
        $guest = $this->guest($outlet, $type, $destination);

        return $this->transaction(function () use ($outlet, $type, $waiterId, $tableId, $covers, $destination, $guest): PosOrder {
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
                'reservation_id' => $guest?->reservationId, 'guest_name' => $guest instanceof ChargeableStay ? mb_substr($guest->guestName, 0, 190) : ($destination?->name !== null ? mb_substr(trim($destination->name), 0, 190) : null),
                'delivery_location' => $guest instanceof ChargeableStay ? mb_substr(implode(', ', $guest->rooms), 0, 190) : ($type->isDelivery() ? mb_substr(trim((string) $destination?->location), 0, 190) : null),
                'delivery_status' => $type->isDelivery() ? DeliveryStatus::Ordered : null,
            ]);

            $table?->forceFill(['status' => TableStatus::Occupied])->save();
            TableStatusChanged::dispatch($order->tenant_id, $outlet->id, $table instanceof DiningTable ? [$table->id] : []);

            return $order;
        });
    }

    /**
     * The in-house guest a room-service order is for; checks the details the order type needs.
     *
     * @throws PosNotAllowed
     */
    private function guest(Outlet $outlet, OrderType $type, ?OrderDestination $destination): ?ChargeableStay
    {
        if ($type === OrderType::LocationDelivery && trim((string) $destination?->location) === '') {
            throw new PosNotAllowed(__('Say where to deliver it (the pool, the beach, a cottage terrace…).'));
        }

        if ($type === OrderType::StaffMeal && trim((string) $destination?->name) === '') {
            throw new PosNotAllowed(__('Name the person or team the staff meal is for.'));
        }

        if ($type !== OrderType::RoomService) {
            return null;
        }

        $stay = collect($this->folios->chargeableStays($outlet->property_id))->firstWhere('reservationId', $destination?->reservationId);

        return $stay instanceof ChargeableStay ? $stay : throw new PosNotAllowed(__('Choose the in-house guest to deliver to.'));
    }
}
