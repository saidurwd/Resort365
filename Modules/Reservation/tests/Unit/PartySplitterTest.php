<?php

use Modules\Reservation\Services\PartySplitter;

/**
 * @param  array<string, array{int, int, int}>  $capacities  key => [adults, children, total]
 * @return array<string, array{adults: int, children: int}>
 */
function splitParty(array $capacities, int $adults, int $children): array
{
    return new PartySplitter()->split(array_map(fn (array $c): array => ['adults' => $c[0], 'children' => $c[1], 'total' => $c[2]], $capacities), $adults, $children);
}

it('gives every item an adult, then fills them in order', function (): void {
    expect(splitParty(['room:1' => [2, 1, 3], 'room:2' => [2, 1, 3]], 3, 1))->toBe([
        'room:1' => ['adults' => 2, 'children' => 1],
        'room:2' => ['adults' => 1, 'children' => 0],
    ]);
});

it('fills a whole cottage up to its capacity', function (): void {
    expect(splitParty(['cottage:7' => [11, 11, 11], 'room:4' => [2, 1, 3]], 8, 3))->toBe([
        'cottage:7' => ['adults' => 7, 'children' => 3],
        'room:4' => ['adults' => 1, 'children' => 0],
    ]);
});

it('keeps guests who do not fit on the last item', function (): void {
    expect(splitParty(['room:1' => [2, 1, 3]], 4, 2))->toBe(['room:1' => ['adults' => 4, 'children' => 2]])
        ->and(splitParty([], 2, 0))->toBe([]);
});
