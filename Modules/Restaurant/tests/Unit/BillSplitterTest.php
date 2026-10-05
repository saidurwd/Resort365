<?php

/*
| Bills always add up to the order (Step 3.6 "done when"): splitting by item, seat, equally or by amount
| never gains or loses a paisa, each bill is consistent, and every line's shares add up to the line.
*/

use Modules\Restaurant\Enums\DiscountType;
use Modules\Restaurant\Services\Allocation;
use Modules\Restaurant\Services\BillSplitter;
use Modules\Restaurant\Services\DiscountAllocator;

/**
 * @param  list<array<string, mixed>>  $bills
 * @param  list<array{id: int, quantity: int, amount: int, discount: int, taxes: array<string, int>}>  $lines
 */
function assertBillsAddUp(array $bills, array $lines, bool $inclusive): void
{
    $codes = array_unique(array_merge(...array_map(fn (array $line): array => array_keys($line['taxes']), $lines)));
    $orderTax = array_sum(array_map(fn (array $line): int => array_sum($line['taxes']), $lines));
    $orderGross = array_sum(array_column($lines, 'amount')) - array_sum(array_column($lines, 'discount')) + ($inclusive ? 0 : $orderTax);

    expect(array_sum(array_column($bills, 'amount')))->toBe(array_sum(array_column($lines, 'amount')))
        ->and(array_sum(array_column($bills, 'discount')))->toBe(array_sum(array_column($lines, 'discount')))
        ->and(array_sum(array_column($bills, 'gross')))->toBe($orderGross);

    foreach ($codes as $code) {
        expect(array_sum(array_map(fn (array $bill): int => $bill['taxes'][$code], $bills)))->toBe(array_sum(array_map(fn (array $line): int => $line['taxes'][$code] ?? 0, $lines)));
    }

    foreach ($bills as $bill) {
        $tax = array_sum($bill['taxes']);
        expect($bill['gross'])->toBe($bill['amount'] - $bill['discount'] + ($inclusive ? 0 : $tax))
            ->and(array_sum(array_column($bill['lines'], 'gross')))->toBe($bill['gross'])
            ->and(array_sum(array_column($bill['lines'], 'amount')))->toBe($bill['amount'])
            ->and(array_sum(array_column($bill['lines'], 'tax')))->toBe($tax);
    }

    foreach ($lines as $line) {
        $shares = array_merge(...array_map(fn (array $bill): array => array_values(array_filter($bill['lines'], fn (array $share): bool => $share['id'] === $line['id'])), $bills));
        expect(array_sum(array_column($shares, 'amount')))->toBe($line['amount']);
    }
}

/**
 * A random order: 1–8 lines, prices in paisa, some discounted, service charge 10% and VAT 15% per line.
 *
 * @return list<array{id: int, quantity: int, amount: int, discount: int, taxes: array<string, int>}>
 */
function randomOrder(int $seed): array
{
    mt_srand($seed);
    $lines = [];

    foreach (range(1, mt_rand(1, 8)) as $id) {
        $quantity = mt_rand(1, 4);
        $amount = $quantity * mt_rand(1, 250000);
        $discount = mt_rand(0, 3) === 0 ? intdiv($amount * mt_rand(1, 50), 100) : 0;
        $net = $amount - $discount;
        $service = intdiv($net * 10 + 50, 100);
        $lines[] = ['id' => $id, 'quantity' => $quantity, 'amount' => $amount, 'discount' => $discount, 'taxes' => ['SC' => $service, 'VAT' => intdiv(($net + $service) * 15 + 50, 100)]];
    }

    return $lines;
}

it('shares cents by largest remainder, always adding up', function (): void {
    expect(Allocation::largestRemainder(100, [1, 1, 1]))->toBe([34, 33, 33])
        ->and(Allocation::largestRemainder(460100, [1, 1, 1, 1]))->toBe([115025, 115025, 115025, 115025])
        ->and(Allocation::largestRemainder(460103, [1, 1, 1, 1]))->toBe([115026, 115026, 115026, 115025])
        ->and(Allocation::largestRemainder(-7, [1, 2]))->toBe([-2, -5])
        ->and(Allocation::largestRemainder(0, [3, 4]))->toBe([0, 0])
        ->and(Allocation::largestRemainder(1000, [700, 300]))->toBe([700, 300]);

    expect(fn () => Allocation::largestRemainder(10, [0, 0]))->toThrow(InvalidArgumentException::class);
});

it('works out discounts and their percentages', function (): void {
    $discounts = new DiscountAllocator;

    expect($discounts->amount(65000, DiscountType::Percent, '10'))->toBe(6500)
        ->and($discounts->amount(33333, DiscountType::Percent, '15'))->toBe(5000)
        ->and($discounts->amount(10000, DiscountType::Amount, '25.50'))->toBe(2550)
        ->and($discounts->amount(10000, DiscountType::Amount, '500'))->toBe(10000)
        ->and($discounts->percentOf(2550, 10000))->toBe('25.50')
        ->and($discounts->percentOf(100, 0))->toBe('0.00')
        ->and($discounts->share(1000, [30000, 10000]))->toBe([750, 250])
        ->and($discounts->share(0, [30000, 10000]))->toBe([0, 0]);
});

it('splits a 4-person table equally into bills that add up to the order', function (): void {
    // 4 × curry 650 + 4 × naan 100 = 3,000.00; service charge 300.00; VAT 495.00 → 3,795.00.
    $lines = [
        ['id' => 1, 'quantity' => 4, 'amount' => 260000, 'discount' => 0, 'taxes' => ['SC' => 26000, 'VAT' => 42900]],
        ['id' => 2, 'quantity' => 4, 'amount' => 40000, 'discount' => 0, 'taxes' => ['SC' => 4000, 'VAT' => 6600]],
    ];
    $bills = (new BillSplitter)->byWeights($lines, false, [1, 1, 1, 1]);

    expect(array_column($bills, 'gross'))->toBe([94875, 94875, 94875, 94875])
        ->and($bills[0]['lines'][0]['quantity'])->toBe('1.000');
    assertBillsAddUp($bills, $lines, false);
});

it('gives the odd paisa of an equal split to the first bills', function (): void {
    $lines = [['id' => 1, 'quantity' => 1, 'amount' => 10000, 'discount' => 0, 'taxes' => ['VAT' => 1501]]];
    $bills = (new BillSplitter)->byWeights($lines, false, [1, 1, 1]);

    expect(array_column($bills, 'gross'))->toBe([3834, 3834, 3833]);
    assertBillsAddUp($bills, $lines, false);
});

it('splits by amount to exactly the amounts asked for', function (): void {
    $lines = randomOrder(7);
    $total = array_sum(array_column((new BillSplitter)->single($lines, false), 'gross'));
    $bills = (new BillSplitter)->byWeights($lines, false, [100000, $total - 100000]);

    expect(array_column($bills, 'gross'))->toBe([100000, $total - 100000]);
    assertBillsAddUp($bills, $lines, false);
});

it('splits by item, units of a line going to different bills', function (): void {
    $lines = [
        ['id' => 1, 'quantity' => 2, 'amount' => 70000, 'discount' => 0, 'taxes' => ['SC' => 7000, 'VAT' => 11550]],
        ['id' => 2, 'quantity' => 1, 'amount' => 85000, 'discount' => 8500, 'taxes' => ['SC' => 7650, 'VAT' => 12623]],
    ];
    $bills = (new BillSplitter)->byLines($lines, false, [1 => [0 => 1, 1 => 1], 2 => [1 => 1]], 2);

    expect($bills[0]['gross'])->toBe(35000 + 3500 + 5775)
        ->and($bills[1]['lines'])->toHaveCount(2)
        ->and($bills[0]['lines'][0]['quantity'])->toBe('1.000');
    assertBillsAddUp($bills, $lines, false);

    expect(fn () => (new BillSplitter)->byLines($lines, false, [1 => [0 => 2]], 2))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new BillSplitter)->byLines($lines, false, [1 => [0 => 2], 2 => [0 => 1]], 2))->toThrow(InvalidArgumentException::class, 'Bill 2 has nothing on it.');
});

it('always adds up, for any order and any split, with prices before or including tax', function (int $seed): void {
    $lines = randomOrder($seed);
    $splitter = new BillSplitter;
    mt_srand($seed * 31);

    foreach ([false, true] as $inclusive) {
        assertBillsAddUp($splitter->single($lines, $inclusive), $lines, $inclusive);
        assertBillsAddUp($splitter->byWeights($lines, $inclusive, array_fill(0, mt_rand(2, 9), 1)), $lines, $inclusive);
        assertBillsAddUp($splitter->byWeights($lines, $inclusive, array_map(fn (): int => mt_rand(1, 50000), range(1, mt_rand(2, 5)))), $lines, $inclusive);

        $count = mt_rand(2, 4);
        $assignments = [];

        foreach ($lines as $line) {
            $units = array_fill(0, $count, 0);

            for ($unit = 0; $unit < $line['quantity']; $unit++) {
                $units[mt_rand(0, $count - 1)]++;
            }

            $assignments[$line['id']] = $units;
        }

        // Every bill needs something: give any empty bill the first unit-holder's line as a shared one.
        foreach (range(0, $count - 1) as $bill) {
            if (! array_filter(array_column($assignments, $bill))) {
                $assignments[$lines[0]['id']][$bill] = 1;
            }
        }

        assertBillsAddUp($splitter->byLines($lines, $inclusive, $assignments, $count), $lines, $inclusive);
    }
})->with(range(1, 60));
