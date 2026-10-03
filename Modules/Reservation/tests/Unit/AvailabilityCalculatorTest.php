<?php

/*
| Step 1.5 "Done when": room booked → its cottage is not available whole; cottage booked whole →
| none of its rooms are available.
*/

use Modules\Property\DTOs\CottageSummary;
use Modules\Property\DTOs\RoomSummary;
use Modules\Property\Enums\BookingMode;
use Modules\Reservation\Services\AvailabilityCalculator;

function aRoom(int $id, int $cottageId, bool $active = true): RoomSummary
{
    return new RoomSummary($id, 1, $cottageId, 10, (string) (100 + $id), null, 2, 2, 1, 3, $active);
}

/**
 * @param  list<int>  $roomIds
 */
function aCottage(int $id, array $roomIds, BookingMode $mode = BookingMode::Both, bool $active = true): CottageSummary
{
    return new CottageSummary($id, 1, 20, 'C'.$id, 'Cottage '.$id, $mode, 3 * count($roomIds), $roomIds, $active);
}

/**
 * @param  list<RoomSummary>  $rooms
 * @param  list<CottageSummary>  $cottages
 * @param  list<int>  $locked
 * @return array{list<int>, list<int>} whole cottage ids, room ids
 */
function sellable(array $rooms, array $cottages, array $locked = []): array
{
    $result = new AvailabilityCalculator()->calculate($rooms, $cottages, $locked);

    return [array_map(fn (CottageSummary $c): int => $c->id, $result->wholeCottages), array_map(fn (RoomSummary $r): int => $r->id, $result->rooms)];
}

it('sells a free cottage whole and room by room', function (): void {
    expect(sellable([aRoom(1, 1), aRoom(2, 1), aRoom(3, 1)], [aCottage(1, [1, 2, 3])]))->toBe([[1], [1, 2, 3]]);
});

it('does not sell the cottage whole once one of its rooms is booked', function (): void {
    expect(sellable([aRoom(1, 1), aRoom(2, 1), aRoom(3, 1)], [aCottage(1, [1, 2, 3])], locked: [2]))->toBe([[], [1, 3]]);
});

it('leaves none of its rooms once the cottage is booked whole', function (): void {
    // A whole-cottage booking locks every room of the cottage.
    expect(sellable([aRoom(1, 1), aRoom(2, 1), aRoom(4, 2)], [aCottage(1, [1, 2]), aCottage(2, [4])], locked: [1, 2]))->toBe([[2], [4]]);
});

it('follows the booking mode', function (): void {
    $rooms = [aRoom(1, 1), aRoom(2, 2), aRoom(3, 3)];
    $cottages = [aCottage(1, [1], BookingMode::RoomsOnly), aCottage(2, [2], BookingMode::WholeOnly), aCottage(3, [3], BookingMode::Both)];

    expect(sellable($rooms, $cottages))->toBe([[2, 3], [1, 3]]);
});

it('ignores inactive rooms and cottages', function (): void {
    // Cottage 1's room 2 is inactive (not among its active rooms): the cottage sells whole with room 1 only.
    $rooms = [aRoom(1, 1), aRoom(2, 1, active: false), aRoom(3, 2), aRoom(4, 3)];
    $cottages = [aCottage(1, [1]), aCottage(2, [3], active: false), aCottage(3, [])];

    expect(sellable($rooms, $cottages))->toBe([[1], [1, 4]]);
});
