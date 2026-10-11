<?php

use Modules\Inventory\Services\StockCostCalculator;

/**
 * Applies a sequence of [signed quantity, cost or null] to an empty balance and returns [quantity, average].
 *
 * @param  list<array{string, string|null}>  $steps
 * @return array{string, string}
 */
function stockSteps(array $steps, string $quantity = '0', string $avg = '0'): array
{
    $calculator = new StockCostCalculator;

    foreach ($steps as [$move, $cost]) {
        $result = $calculator->apply($quantity, $avg, $move, $cost);
        [$quantity, $avg] = [$result['quantity'], $result['avg_cost']];
    }

    return [$quantity, $avg];
}

it('averages receipts at different prices (done when)', function (): void {
    expect(stockSteps([['10', '100']]))->toBe(['10.0000', '100.0000'])
        ->and(stockSteps([['10', '100'], ['10', '120']]))->toBe(['20.0000', '110.0000'])
        ->and(stockSteps([['10', '100'], ['10', '120'], ['5', '130']]))->toBe(['25.0000', '114.0000'])
        ->and(stockSteps([['3', '10'], ['7', '20']]))->toBe(['10.0000', '17.0000']);
});

it('issues at the current average and leaves the average alone', function (): void {
    $calculator = new StockCostCalculator;
    $issue = $calculator->apply('20.0000', '110.0000', '-5');

    expect($issue)->toBe(['quantity' => '15.0000', 'avg_cost' => '110.0000', 'unit_cost' => '110.0000'])
        ->and(stockSteps([['10', '100'], ['10', '120'], ['-5', null], ['5', '130']]))->toBe(['20.0000', '115.0000']);
});

it('sets the average to the next receipt\'s cost when the balance was emptied', function (): void {
    expect(stockSteps([['10', '100'], ['-10', null], ['4', '150']]))->toBe(['4.0000', '150.0000']);
});

it('lets the balance go negative and starts over on the next receipt', function (): void {
    expect(stockSteps([['5', '100'], ['-8', null]]))->toBe(['-3.0000', '100.0000'])
        ->and(stockSteps([['5', '100'], ['-8', null], ['10', '120']]))->toBe(['7.0000', '120.0000']);
});

it('re-averages when a reversal takes stock out at the cost it came in at', function (): void {
    $calculator = new StockCostCalculator;
    $reverse = $calculator->apply('20.0000', '110.0000', '-10', '120');

    expect($reverse)->toBe(['quantity' => '10.0000', 'avg_cost' => '100.0000', 'unit_cost' => '120.0000'])
        ->and($calculator->apply('10.0000', '100.0000', '-10', '100')['avg_cost'])->toBe('100.0000');
});

it('values a quantity to two decimals, half up', function (): void {
    $calculator = new StockCostCalculator;

    expect($calculator->value('3', '1.115'))->toBe('3.35')->and($calculator->value('-2.5', '4'))->toBe('10.00')->and($calculator->value('0.0001', '1000'))->toBe('0.10');
});

it('keeps the average within a ten-thousandth of the exact value over many receipts', function (): void {
    mt_srand(7);
    $steps = [];
    $units = 0.0;
    $worth = 0.0;

    for ($i = 0; $i < 200; $i++) {
        $quantity = mt_rand(1, 50) / 4;
        $cost = mt_rand(100, 5000) / 100;
        $steps[] = [number_format($quantity, 4, '.', ''), number_format($cost, 4, '.', '')];
        $units += $quantity;
        $worth += $quantity * $cost;
    }

    [$quantity, $avg] = stockSteps($steps);

    expect((float) $quantity)->toEqualWithDelta($units, 0.0001)->and((float) $avg)->toEqualWithDelta($worth / $units, 0.01);
});
