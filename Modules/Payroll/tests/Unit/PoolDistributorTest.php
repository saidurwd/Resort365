<?php

/*
| Sharing a pool (Step 6.6): by weight, to the cent, always adding up to the pool.
*/

use Modules\Payroll\Services\Calculation\PoolDistributor;

it('shares a pool by weight and gives the cents left over to the largest remainders', function (): void {
    $share = new PoolDistributor;

    expect($share->distribute('100000.00', [1 => '1.0000', 2 => '0.5161']))->toBe([1 => '65958.71', 2 => '34041.29'])
        ->and($share->distribute('100.00', [1 => '1', 2 => '1', 3 => '1']))->toBe([1 => '33.34', 2 => '33.33', 3 => '33.33'])
        ->and($share->distribute('10.00', [1 => '1', 2 => '2', 3 => '3']))->toBe([1 => '1.67', 2 => '3.33', 3 => '5.00'])
        ->and($share->distribute('0.05', [1 => '1', 2 => '1', 3 => '1']))->toBe([1 => '0.02', 2 => '0.02', 3 => '0.01'])
        ->and($share->distribute('100000.00', [1 => '2.0000', 2 => '1.0000']))->toBe([1 => '66666.67', 2 => '33333.33']);
});

it('leaves out weights of zero and gives nothing when there is nothing to share', function (): void {
    $share = new PoolDistributor;

    expect($share->distribute('90.00', [1 => '1', 2 => '0', 3 => '2']))->toBe([1 => '30.00', 3 => '60.00'])
        ->and($share->distribute('0.00', [1 => '1', 2 => '1']))->toBe([1 => '0.00', 2 => '0.00'])
        ->and($share->distribute('50.00', []))->toBe([])
        ->and($share->distribute('50.00', [1 => '0', 2 => '-1']))->toBe([]);
});

it('always adds up to the pool exactly, whatever the weights', function (): void {
    $share = new PoolDistributor;
    mt_srand(66);

    foreach (range(1, 200) as $case) {
        $people = random_int(1, 25);
        $weights = [];

        foreach (range(1, $people) as $id) {
            $weights[$id] = number_format(random_int(1, 90000) / 10000, 4, '.', '');
        }

        $total = number_format(random_int(0, 9999999) / 100, 2, '.', '');
        $amounts = $share->distribute($total, $weights);
        $sum = array_reduce($amounts, fn (string $carry, string $amount): string => bcadd($carry, $amount, 2), '0.00');

        expect($sum)->toBe($total)->and(count($amounts))->toBe($people);
    }
});

it('gives the same answer every time for the same weights', function (): void {
    $share = new PoolDistributor;
    $weights = [3 => '1', 1 => '1', 2 => '1'];

    expect($share->distribute('100.00', $weights))->toBe($share->distribute('100.00', $weights))->and($share->distribute('100.00', $weights)[1])->toBe('33.34');
});
