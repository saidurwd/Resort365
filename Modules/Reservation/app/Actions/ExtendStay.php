<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\StayNotPossible;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Services\ReservationLogger;
use Modules\Reservation\Services\StayRepricer;

/**
 * Extends an in-house stay to a later departure (ARCHITECTURE §6.7): the extra nights of every room
 * and cottage are priced with each item's rate plan and locked. If another booking holds any of
 * those room-nights, nothing changes.
 */
class ExtendStay extends Action
{
    public function __construct(
        private readonly StayRepricer $repricer,
        private readonly InventoryCatalog $catalog,
        private readonly ReservationLogger $logger,
    ) {}

    /**
     * @throws StayNotPossible
     */
    public function handle(Reservation $reservation, CarbonImmutable $checkOut, ?int $userId = null): Reservation
    {
        try {
            return $this->transaction(function () use ($reservation, $checkOut, $userId): Reservation {
                $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
                $from = $locked->check_out->toImmutable();

                if ($locked->status !== ReservationStatus::CheckedIn) {
                    throw new StayNotPossible(__('Only a stay in house is extended here; change the stay before arrival instead.'));
                }

                if (! $checkOut->gt($from)) {
                    throw new StayNotPossible(__('Choose a departure after :date.', ['date' => $from->format('d M Y')]));
                }

                $before = $locked->grand_total;
                $cottages = collect($this->catalog->cottages($locked->property_id))->keyBy('id');

                foreach (ReservationItem::query()->where('reservation_id', $locked->id)->get() as $item) {
                    $this->repricer->addNights($item, $this->repricer->quote($locked, $item, $from, $checkOut));
                    $roomIds = $item->item_type === ItemType::Room ? [(int) $item->room_id] : array_map(intval(...), $cottages->get($item->cottage_id)->roomIds ?? []);
                    $this->repricer->lock($item, $roomIds, $from, $checkOut);
                    $item->forceFill(['check_out' => $checkOut->toDateString()])->save();
                }

                $locked->forceFill(['check_out' => $checkOut->toDateString()])->save();
                $this->repricer->refresh($locked);
                $this->logger->log($locked, ReservationLogAction::Modified, __('Stay extended to :date; total :old → :new.', [
                    'date' => $checkOut->format('d M Y'), 'old' => $before, 'new' => $locked->grand_total,
                ]), ['check_out' => [$from->toDateString(), $checkOut->toDateString()], 'grand_total' => [$before, $locked->grand_total]], $userId);

                return $locked;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw new StayNotPossible(__('Some rooms are booked by someone else on the extra nights.'));
        } catch (BookingNotPossible $exception) {
            throw new StayNotPossible($exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}
