<?php

use Modules\Property\Services\RoomNumberSequence;

it('counts on from the first room number', function (string $first, int $count, array $expected): void {
    expect(new RoomNumberSequence()->generate($first, $count))->toBe($expected);
})->with([
    'numeric' => ['101', 3, ['101', '102', '103']],
    'one room' => ['701', 1, ['701']],
    'prefix and zero padding' => ['A08', 3, ['A08', 'A09', 'A10']],
    'padding grows when needed' => ['099', 2, ['099', '100']],
    'single digit' => ['9', 2, ['9', '10']],
    'dashed prefix' => ['C1-01', 2, ['C1-01', 'C1-02']],
]);

it('refuses a first number that does not end in digits', function (): void {
    new RoomNumberSequence()->generate('PH', 2);
})->throws(InvalidArgumentException::class);
