<?php

namespace Modules\Reservation\Contracts;

use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\DTOs\RoomNightCharge;
use Modules\Reservation\Exceptions\StayNotPossible;

/**
 * Stay operations for the front office (ARCHITECTURE §5.7). The Reservation module owns the
 * booking's status and room locks; FrontOffice drives them through this contract.
 */
interface StayOperations
{
    /**
     * Checks a confirmed booking in (arrival on or before the property's business date).
     *
     * @throws StayNotPossible
     */
    public function checkIn(int $reservationId, ?int $userId = null): ReservationSummary;

    /**
     * Gives a room item another free room of the same room type for the rest of its stay; the
     * price is kept. Whole-cottage items keep their cottage.
     *
     * @throws StayNotPossible
     */
    public function changeRoom(int $reservationItemId, int $roomId, ?int $userId = null): void;

    /**
     * The booking's room items as id => [label, room id, room type id], for room confirmation.
     *
     * @return array<int, array{label: string, room_id: int|null, room_type_id: int|null}>
     */
    public function roomItems(int $reservationId): array;

    /**
     * Every room the booking occupies (a whole cottage counts all its rooms).
     *
     * @return list<int>
     */
    public function roomIds(int $reservationId): array;

    /**
     * The booking's nights not yet posted to a folio, in date order.
     *
     * @return list<RoomNightCharge>
     */
    public function unpostedNights(int $reservationId): array;

    /**
     * Marks nights as posted to a folio (so they are never posted twice).
     *
     * @param  list<int>  $nightIds
     */
    public function markNightsPosted(array $nightIds): void;

    /**
     * Checks an in-house booking out and releases its rooms (departure today or earlier).
     *
     * @throws StayNotPossible
     */
    public function checkOut(int $reservationId, ?int $userId = null): ReservationSummary;
}
