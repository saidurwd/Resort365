<?php

/*
| The 3-way match and the return split (Step 5.6), pure arithmetic.
*/
use Modules\Procurement\Services\ReturnSplitter;
use Modules\Procurement\Services\ThreeWayMatch;

it('allows billing what is received, less returns and earlier bills', function (): void {
    $line = ['billed' => '10', 'price' => '100', 'received' => '30', 'returned' => '2', 'already_billed' => '18', 'po_price' => '100'];
    $result = (new ThreeWayMatch)->check(['a' => $line], '0', '2');

    expect($result['lines']['a']['allowed'])->toBe('10.0000')->and($result['lines']['a']['quantity_ok'])->toBeTrue()->and($result['quantity_blocked'])->toBeFalse()->and($result['price_flagged'])->toBeFalse();

    $over = (new ThreeWayMatch)->check(['a' => [...$line, 'billed' => '10.5']], '0', '2');
    expect($over['lines']['a']['quantity_ok'])->toBeFalse()->and($over['lines']['a']['excess'])->toBe('0.5000')->and($over['quantity_blocked'])->toBeTrue();
});

it('applies the quantity tolerance, and never lets an over-billed line go negative', function (): void {
    $line = ['billed' => '10.4', 'price' => '100', 'received' => '10', 'returned' => '0', 'already_billed' => '0', 'po_price' => '100'];

    expect((new ThreeWayMatch)->check(['a' => $line], '5', '2')['quantity_blocked'])->toBeFalse()
        ->and((new ThreeWayMatch)->check(['a' => $line], '3', '2')['quantity_blocked'])->toBeTrue()
        ->and((new ThreeWayMatch)->check(['a' => [...$line, 'already_billed' => '12', 'billed' => '1']], '0', '2')['lines']['a']['allowed'])->toBe('0.0000');
});

it('flags a price above the order price by more than the tolerance only', function (): void {
    $line = ['billed' => '1', 'price' => '102', 'received' => '1', 'returned' => '0', 'already_billed' => '0', 'po_price' => '100'];
    $check = fn (string $price, string $tolerance) => (new ThreeWayMatch)->check(['a' => [...$line, 'price' => $price]], '0', $tolerance);

    expect($check('102', '2')['price_flagged'])->toBeFalse()->and($check('102.0001', '2')['price_flagged'])->toBeTrue()->and($check('90', '0')['price_flagged'])->toBeFalse()->and($check('100.01', '0')['lines']['a']['price_limit'])->toBe('100.0000');
});

it('returns unbilled goods first, then billed ones with their tax', function (): void {
    $splitter = new ReturnSplitter;

    // 30 received, 20 billed, 0 returned: 10 are unbilled.
    $inside = $splitter->split('4', '30', '0', '20', '100', '0.15');
    expect($inside)->toBe(['value' => '400.00', 'grni_value' => '400.00', 'billed_value' => '0.00', 'tax_value' => '0.00', 'grni_quantity' => '4.0000', 'billed_quantity' => '0.0000']);

    $across = $splitter->split('14', '30', '0', '20', '100', '0.15');
    expect($across['value'])->toBe('1400.00')->and($across['grni_value'])->toBe('1000.00')->and($across['billed_value'])->toBe('400.00')->and($across['tax_value'])->toBe('60.00')->and($across['billed_quantity'])->toBe('4.0000');

    $afterEarlier = $splitter->split('5', '30', '8', '20', '100', '0');
    expect($afterEarlier['grni_quantity'])->toBe('2.0000')->and($afterEarlier['billed_quantity'])->toBe('3.0000');
});
