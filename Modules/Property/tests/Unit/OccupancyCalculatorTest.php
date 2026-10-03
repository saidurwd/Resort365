<?php

use Modules\Property\DTOs\RoomCapacity;
use Modules\Property\Services\OccupancyCalculator;

/*
| ARCHITECTURE §5.4: a room uses its own adults/children or its room type's; the room type's
| max occupancy caps the total. A cottage takes its override, or the sum of its active rooms.
*/

it('uses the room type\'s values when the room has none', function (): void {
    expect(new OccupancyCalculator()->room(null, null, 2, 1, 3))->toEqual(new RoomCapacity(2, 1, 3));
});

it('uses the room\'s own adults and children', function (): void {
    expect(new OccupancyCalculator()->room(1, 0, 2, 1, 3))->toEqual(new RoomCapacity(1, 0, 1))
        ->and(new OccupancyCalculator()->room(null, 0, 2, 1, 3))->toEqual(new RoomCapacity(2, 0, 2));
});

it('never lets a room exceed the room type\'s max occupancy', function (): void {
    // Family Suite: 4 adults, 2 children, at most 5 guests.
    expect(new OccupancyCalculator()->room(null, null, 4, 2, 5))->toEqual(new RoomCapacity(4, 2, 5))
        ->and(new OccupancyCalculator()->room(6, 3, 4, 2, 5))->toEqual(new RoomCapacity(5, 3, 5));
});

it('adds up the rooms of a cottage', function (): void {
    expect(new OccupancyCalculator()->cottage(null, [5, 3, 3]))->toBe(11)
        ->and(new OccupancyCalculator()->cottage(null, [2]))->toBe(2)
        ->and(new OccupancyCalculator()->cottage(null, []))->toBe(0);
});

it('uses the cottage\'s override instead of the rooms', function (): void {
    expect(new OccupancyCalculator()->cottage(10, [5, 3, 3]))->toBe(10);
});
