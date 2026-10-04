<?php

/*
| TapeChartBuilder: inventory locks → bars (ARCHITECTURE §6.8), without the database.
| The window starts on 10 Nov and shows 7 days (columns 0–6).
*/

use Carbon\CarbonImmutable;
use Modules\Reservation\Services\TapeChartBuilder;

/**
 * @param  list<string>  $dates
 * @return list<array{room_id: int, date: string, type: string, reservation_id: int|null, item_id: int|null, block: string|null}>
 */
function chartLocks(int $room, array $dates, ?int $item = 1, string $type = 'reservation', ?string $block = null): array
{
    return array_map(fn (string $date): array => ['room_id' => $room, 'date' => $date, 'type' => $type,
        'reservation_id' => $item !== null ? 100 + $item : null, 'item_id' => $item, 'block' => $block], $dates);
}

/**
 * @param  list<array<string, mixed>>  $locks
 * @return list<array<string, mixed>>
 */
function chartBars(array $locks): array
{
    return (new TapeChartBuilder)->bars($locks, CarbonImmutable::parse('2026-11-10'), 7);
}

it('merges the consecutive nights of one booking on one room into a bar', function (): void {
    $bars = chartBars(chartLocks(4, ['2026-11-13', '2026-11-11', '2026-11-12']));

    expect($bars)->toHaveCount(1)
        ->and([$bars[0]['room_id'], $bars[0]['start'], $bars[0]['span'], $bars[0]['item_id'], $bars[0]['reservation_id']])->toBe([4, 1, 3, 1, 101])
        ->and([$bars[0]['continues_before'], $bars[0]['continues_after']])->toBe([false, false]);
});

it('splits a booking with a gap, and keeps rooms and bookings apart', function (): void {
    $bars = chartBars([
        ...chartLocks(4, ['2026-11-10', '2026-11-11', '2026-11-14']),
        ...chartLocks(4, ['2026-11-12', '2026-11-13'], item: 2),
        ...chartLocks(5, ['2026-11-10']),
    ]);

    expect(array_map(fn (array $bar): array => [$bar['room_id'], $bar['item_id'], $bar['start'], $bar['span']], $bars))->toBe([
        [4, 1, 0, 2], [4, 2, 2, 2], [4, 1, 4, 1], [5, 1, 0, 1],
    ]);
});

it('clips bars to the window and flags the parts outside it', function (): void {
    $bars = chartBars([
        ...chartLocks(4, ['2026-11-08', '2026-11-09', '2026-11-10', '2026-11-11']),
        ...chartLocks(5, ['2026-11-15', '2026-11-16', '2026-11-17', '2026-11-18'], item: 2),
        ...chartLocks(6, ['2026-11-01', '2026-11-02'], item: 3),
        ...chartLocks(7, ['2026-11-17'], item: 4),
    ]);

    expect(array_map(fn (array $bar): array => [$bar['room_id'], $bar['start'], $bar['span'], $bar['continues_before'], $bar['continues_after']], $bars))->toBe([
        [4, 0, 2, true, false],
        [5, 5, 2, false, true],
    ]);
});

it('groups blocks by type and note', function (): void {
    $bars = chartBars([
        ...chartLocks(4, ['2026-11-10', '2026-11-11'], item: null, type: 'out_of_order', block: 'Leaking roof'),
        ...chartLocks(4, ['2026-11-12'], item: null, type: 'owner_block'),
    ]);

    expect(array_map(fn (array $bar): array => [$bar['type'], $bar['start'], $bar['span'], $bar['block'], $bar['item_id']], $bars))->toBe([
        ['out_of_order', 0, 2, 'Leaking roof', null],
        ['owner_block', 2, 1, null, null],
    ]);
});
