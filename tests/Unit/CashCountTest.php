<?php

/*
| CashCount: closing a cashier shift.
*/

use App\Support\Cash\CashCount;

it('adds up the notes and coins counted', function (): void {
    expect((new CashCount)->counted(['1000' => 12, '500' => '3', '100' => null, '20' => 4, '1' => 7]))->toBe('13587.00');
});

it('refuses negative counts', function (): void {
    (new CashCount)->counted(['500' => -1]);
})->throws(InvalidArgumentException::class);

it('expects the float plus cash received less cash refunded, and gives the variance', function (): void {
    $count = new CashCount;
    $expected = $count->expected('5000.00', '9100.00', '600.00');

    expect($expected)->toBe('13500.00')
        ->and($count->variance('13587.00', $expected))->toBe('87.00')
        ->and($count->variance('13400.00', $expected))->toBe('-100.00')
        ->and($count->variance('13500', $expected))->toBe('0.00');
});

it('reads the denominations setting, largest first', function (): void {
    expect((new CashCount)->denominations(' 100, 1000,abc, 0, 500,100, 0.5'))->toBe(['1000', '500', '100', '0.5']);
});
