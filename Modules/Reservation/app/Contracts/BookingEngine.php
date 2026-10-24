<?php

namespace Modules\Reservation\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Rates\DTOs\CancellationQuote;
use Modules\Reservation\DTOs\AvailabilityResult;
use Modules\Reservation\DTOs\AvailabilitySearch;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\BookingQuote;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\DepositBelowMinimum;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Reservation\Exceptions\RoomNoLongerAvailable;

/**
 * Searching and booking for other modules (Step 8.2b; the public website), with the same rules as the booking wizard: availability
 * per sales channel, items checked against it, prices and deposits from the BookingQuoter, bookings by CreateReservation, and
 * cancelling within the booking's policy.
 */
interface BookingEngine
{
    public function availability(AvailabilitySearch $search): ?AvailabilityResult;

    /**
     * The asked rooms and whole cottages as BookingItems, each checked against availability for its party (a room may be asked for
     * by `room_type_id`: the first free room of the type is taken).
     *
     * @param  list<array{type: string, room_id?: int|null, room_type_id?: int|null, cottage_id?: int|null, adults: int, children?: int|null}>  $rows
     * @return list<BookingItem>
     *
     * @throws ValidationException on `items.N` or `rate_plan_id`
     */
    public function resolveItems(int $propertyId, int $ratePlanId, CarbonImmutable $checkIn, CarbonImmutable $checkOut, array $rows, ?string $promoCode = null, string $channel = 'front_desk'): array;

    /**
     * @throws BookingNotPossible
     */
    public function quote(NewReservation $data): BookingQuote;

    /**
     * @throws BookingNotPossible|DepositBelowMinimum|RoomNoLongerAvailable
     */
    public function book(NewReservation $data): ReservationSummary;

    /**
     * What cancelling the booking now would cost, or null when it cannot be cancelled (not found, or no longer tentative or confirmed).
     */
    public function cancellationQuote(int $reservationId): ?CancellationQuote;

    /**
     * @throws ReservationNotChangeable
     */
    public function cancel(int $reservationId, string $reason): ReservationSummary;
}
