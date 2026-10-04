<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Exceptions\StayNotPossible;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Services\ReservationLogger;
use Modules\Reservation\Services\StayRepricer;

/**
 * Early departure (ARCHITECTURE §6.7): the stay ends on an earlier date (at least one night after
 * arrival, no earlier than the business date). The rooms are released from that date on and the
 * nights not on a folio yet are removed, so the bill only holds the nights stayed.
 */
class ShortenStay extends Action
{
    public function __construct(
        private readonly StayRepricer $repricer,
        private readonly ReservationLogger $logger,
    ) {}

    /**
     * @throws StayNotPossible
     */
    public function handle(Reservation $reservation, CarbonImmutable $checkOut, ?int $userId = null): Reservation
    {
        return $this->transaction(function () use ($reservation, $checkOut, $userId): Reservation {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $old = $locked->check_out->toImmutable();
            $earliest = $locked->check_in->toImmutable()->addDay()->max($this->repricer->businessDate($locked->property_id));

            if ($locked->status !== ReservationStatus::CheckedIn) {
                throw new StayNotPossible(__('Only a stay in house is shortened here; change the stay before arrival instead.'));
            }

            if ($checkOut->lt($earliest) || ! $checkOut->lt($old)) {
                throw new StayNotPossible(__('Choose a departure from :from up to the day before :to.', ['from' => $earliest->format('d M Y'), 'to' => $old->format('d M Y')]));
            }

            $before = $locked->grand_total;

            foreach (ReservationItem::query()->where('reservation_id', $locked->id)->get() as $item) {
                $this->repricer->removeNightsFrom($item, $checkOut->toDateString());
                $item->forceFill(['check_out' => $checkOut->toDateString()])->save();
            }

            InventoryLock::query()->where('reservation_id', $locked->id)->where('stay_date', '>=', $checkOut->toDateString())->delete();
            $locked->forceFill(['check_out' => $checkOut->toDateString()])->save();
            $this->repricer->refresh($locked);

            $this->logger->log($locked, ReservationLogAction::Modified, __('Leaving early on :date; total :old → :new.', [
                'date' => $checkOut->format('d M Y'), 'old' => $before, 'new' => $locked->grand_total,
            ]), ['check_out' => [$old->toDateString(), $checkOut->toDateString()], 'grand_total' => [$before, $locked->grand_total]], $userId);

            return $locked;
        });
    }
}
