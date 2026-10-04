<?php

namespace Modules\Reservation\Contracts;

use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\DTOs\RoomNightCharge;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Exceptions\StayNotPossible;

/**
 * Stay operations for the front office (ARCHITECTURE §5.7). The Reservation module owns the
 * booking's status and room locks; FrontOffice drives them through this contract.
 */
interface StayOperations
{
    /**
     * Checks a confirmed booking in (arrival on or before the property's business date): every room
     * and cottage not in yet, or one item (a group arriving room by room).
     *
     * @return list<int> the items checked in now
     *
     * @throws StayNotPossible
     */
    public function checkIn(int $reservationId, ?int $userId = null, ?int $itemId = null): array;

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
     * @return array<int, array{label: string, room_id: int|null, room_type_id: int|null, status: ReservationStatus}>
     */
    public function roomItems(int $reservationId): array;

    /**
     * Every room the booking (or these items of it) occupies; a whole cottage counts all its rooms.
     *
     * @param  list<int>|null  $itemIds
     * @return list<int>
     */
    public function roomIds(int $reservationId, ?array $itemIds = null): array;

    /**
     * Moves an in-house room to another room for the remaining nights (rate kept unless reprice).
     *
     * @throws StayNotPossible
     */
    public function moveRoom(int $reservationItemId, int $roomId, bool $reprice = false, ?int $userId = null): void;

    /**
     * Extends an in-house stay to a later departure (Y-m-d), pricing and locking the extra nights.
     *
     * @throws StayNotPossible
     */
    public function extendStay(int $reservationId, string $checkOut, ?int $userId = null): ReservationSummary;

    /**
     * Ends an in-house stay early (Y-m-d), releasing the rooms and removing unbilled nights.
     *
     * @throws StayNotPossible
     */
    public function shortenStay(int $reservationId, string $checkOut, ?int $userId = null): ReservationSummary;

    /**
     * The booking's nights not yet posted to a folio, in date order; with upTo (Y-m-d), only the
     * nights up to and including that date (night audit).
     *
     * @return list<RoomNightCharge>
     */
    public function unpostedNights(int $reservationId, ?string $upTo = null): array;

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

    /**
     * Marks an expected arrival that did not come as a no-show (night audit of $date, Y-m-d): the
     * no-show fee is kept and the rooms are released from the next night.
     *
     * @throws StayNotPossible
     */
    public function markNoShow(int $reservationId, string $date, ?int $userId = null): ReservationSummary;

    /**
     * Cancels the property's tentative bookings whose deposit hold ran out.
     *
     * @return int how many were cancelled
     */
    public function expireHolds(int $propertyId): int;
}
