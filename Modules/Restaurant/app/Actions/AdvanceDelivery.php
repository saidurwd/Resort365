<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Enums\DeliveryStatus;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Events\DeliveryStatusChanged;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;

/**
 * Moves a room-service or location-delivery order along (ARCHITECTURE §5.10.4): ordered → preparing
 * (the kitchen starts, see ProgressKot::preparing) → out for delivery → delivered, with the times. Only
 * an order with something sent to the kitchen can go out; the outlet's screens are told.
 */
class AdvanceDelivery extends Action
{
    /**
     * @throws PosNotAllowed
     */
    public function handle(PosOrder $order, DeliveryStatus $to): PosOrder
    {
        return $this->transaction(function () use ($order, $to): PosOrder {
            $locked = PosOrder::query()->lockForUpdate()->findOrFail($order->id);
            $from = $locked->delivery_status;

            if (! $locked->order_type->isDelivery() || $from === null) {
                throw new PosNotAllowed(__('This order is not a delivery.'));
            }

            $allowed = match ($to) {
                DeliveryStatus::Preparing => $from === DeliveryStatus::Ordered,
                DeliveryStatus::OutForDelivery => in_array($from, [DeliveryStatus::Ordered, DeliveryStatus::Preparing], true),
                DeliveryStatus::Delivered => $from === DeliveryStatus::OutForDelivery,
                DeliveryStatus::Ordered => false,
            };

            if (! $allowed) {
                throw new PosNotAllowed(__('A delivery that is :from cannot become :to.', ['from' => mb_strtolower($from->label()), 'to' => mb_strtolower($to->label())]));
            }

            if ($to === DeliveryStatus::OutForDelivery && ! PosOrderLine::query()->where('pos_order_id', $locked->id)->where('status', '!=', OrderLineStatus::Pending->value)
                ->where('status', '!=', OrderLineStatus::Voided->value)->exists()) {
                throw new PosNotAllowed(__('Send the order to the kitchen first.'));
            }

            $locked->forceFill(['delivery_status' => $to, ...match ($to) {
                DeliveryStatus::OutForDelivery => ['out_for_delivery_at' => now()],
                DeliveryStatus::Delivered => ['delivered_at' => now()],
                default => [],
            }])->save();

            DeliveryStatusChanged::dispatch($locked->tenant_id, $locked->outlet_id, $locked->id, $to->value);

            return $locked;
        });
    }
}
