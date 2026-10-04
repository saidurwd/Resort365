<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Exceptions\StayNotPossible;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Services\ItemLabels;
use Modules\Reservation\Services\ReservationLogger;

/**
 * Checks a booking in (ARCHITECTURE §6.4): all of its rooms and cottages, or one at a time (a group
 * arriving room by room, §6.4 "item-level statuses"). The booking must be confirmed (deposit paid or
 * waived) and arrive on the property's business date or earlier; it is Checked in as soon as its
 * first item is, the other items stay Confirmed until they arrive.
 */
class CheckInReservation extends Action
{
    public function __construct(
        private readonly PropertyDirectory $properties,
        private readonly ReservationLogger $logger,
        private readonly ItemLabels $labels,
    ) {}

    /**
     * @param  int|null  $itemId  one room or cottage; null = every item not checked in yet
     * @return list<int> the ids of the items checked in now
     *
     * @throws StayNotPossible
     */
    public function handle(int $reservationId, ?int $userId = null, ?int $itemId = null): array
    {
        return $this->transaction(function () use ($reservationId, $userId, $itemId): array {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservationId);

            if (! in_array($reservation->status, [ReservationStatus::Confirmed, ReservationStatus::CheckedIn], true)) {
                throw new StayNotPossible($reservation->status === ReservationStatus::Tentative
                    ? __('Booking :code is still tentative: take the deposit first.', ['code' => $reservation->code])
                    : __('Booking :code is :status.', ['code' => $reservation->code, 'status' => strtolower($reservation->status->label())]));
            }

            $businessDate = $this->properties->find($reservation->property_id)->businessDate ?? now()->toDateString();

            if ($reservation->check_in->toDateString() > $businessDate) {
                throw new StayNotPossible(__('Booking :code arrives on :date; it cannot be checked in before then.', ['code' => $reservation->code, 'date' => $reservation->check_in->format('d M Y')]));
            }

            $items = ReservationItem::query()->where('reservation_id', $reservation->id)->where('status', ReservationStatus::Confirmed->value)
                ->when($itemId !== null, fn ($query) => $query->whereKey($itemId))->lockForUpdate()->get();

            if ($items->isEmpty()) {
                throw new StayNotPossible($itemId !== null ? __('That room is already checked in.') : __('Booking :code is already in house.', ['code' => $reservation->code]));
            }

            ReservationItem::query()->whereIn('id', $items->pluck('id'))->update(['status' => ReservationStatus::CheckedIn->value]);
            $first = $reservation->status === ReservationStatus::Confirmed;

            if ($first) {
                $reservation->forceFill(['status' => ReservationStatus::CheckedIn, 'checked_in_at' => now()])->save();
            }

            $this->logger->log($reservation, ReservationLogAction::CheckedIn, $itemId !== null
                ? __(':unit checked in.', ['unit' => $this->labels->of($items->first())])
                : __('Checked in.'), $first ? ['status' => [ReservationStatus::Confirmed->value, ReservationStatus::CheckedIn->value]] : [], $userId);

            return $items->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
        });
    }
}
