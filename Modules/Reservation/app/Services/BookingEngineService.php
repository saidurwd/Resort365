<?php

namespace Modules\Reservation\Services;

use Carbon\CarbonImmutable;
use Modules\Rates\DTOs\CancellationQuote;
use Modules\Reservation\Actions\CancelReservation;
use Modules\Reservation\Actions\CreateReservation;
use Modules\Reservation\Contracts\BookingEngine;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\AvailabilityResult;
use Modules\Reservation\DTOs\AvailabilitySearch;
use Modules\Reservation\DTOs\BookingQuote;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Reservation\Models\Reservation;

class BookingEngineService implements BookingEngine
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingItemsResolver $items,
        private readonly BookingQuoter $quoter,
        private readonly CreateReservation $create,
        private readonly CancelReservation $cancellations,
        private readonly ReservationLookup $lookup,
    ) {}

    public function availability(AvailabilitySearch $search): ?AvailabilityResult
    {
        return $this->availability->search($search);
    }

    public function resolveItems(int $propertyId, int $ratePlanId, CarbonImmutable $checkIn, CarbonImmutable $checkOut, array $rows, ?string $promoCode = null, string $channel = 'front_desk'): array
    {
        return $this->items->resolve($propertyId, $ratePlanId, $checkIn, $checkOut, $rows, $promoCode, $channel);
    }

    public function quote(NewReservation $data): BookingQuote
    {
        return $this->quoter->quote($data);
    }

    public function book(NewReservation $data): ReservationSummary
    {
        return $this->summary($this->create->handle($data)->id);
    }

    public function cancellationQuote(int $reservationId): ?CancellationQuote
    {
        $reservation = Reservation::query()->find($reservationId);

        return $reservation instanceof Reservation && $reservation->isChangeable() ? $this->cancellations->quote($reservation) : null;
    }

    public function cancel(int $reservationId, string $reason): ReservationSummary
    {
        $reservation = Reservation::query()->find($reservationId) ?? throw new ReservationNotChangeable(__('This booking cannot be found.'));
        $this->cancellations->handle($reservation, $reason);

        return $this->summary($reservationId);
    }

    private function summary(int $reservationId): ReservationSummary
    {
        return $this->lookup->find($reservationId) ?? throw new ReservationNotChangeable(__('This booking cannot be found.'));
    }
}
