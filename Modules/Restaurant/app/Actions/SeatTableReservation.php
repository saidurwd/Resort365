<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Enums\OrderType;
use Modules\Restaurant\Enums\TableReservationStatus;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\TableReservation;

/**
 * Seats a booked party (ARCHITECTURE §5.10.12): opens a dine-in order at the reserved table with the
 * party's covers and links it; the reservation is completed when the order is settled (BillSettlement).
 * A table can also be chosen now (for a reservation made without one).
 */
class SeatTableReservation extends Action
{
    public function __construct(
        private readonly OpenOrder $open,
    ) {}

    /**
     * @throws PosNotAllowed
     */
    public function handle(TableReservation $reservation, int $waiterId, ?int $tableId = null): PosOrder
    {
        return $this->transaction(function () use ($reservation, $waiterId, $tableId): PosOrder {
            $locked = TableReservation::query()->lockForUpdate()->with('outlet')->findOrFail($reservation->id);

            if ($locked->status !== TableReservationStatus::Booked) {
                throw new PosNotAllowed(__('This reservation is :status.', ['status' => mb_strtolower($locked->status->label())]));
            }

            $table = $tableId ?? $locked->dining_table_id ?? throw new PosNotAllowed(__('Choose the table to seat them at.'));
            $order = $this->open->handle($locked->outlet, OrderType::DineIn, $waiterId, $table, $locked->party_size);
            $locked->forceFill(['dining_table_id' => $table, 'status' => TableReservationStatus::Seated, 'pos_order_id' => $order->id])->save();

            return $order;
        });
    }
}
