<?php

/*
| Recipes and plate costs (Step 5.7), pure arithmetic.
*/
use Modules\Restaurant\Services\PlateCostCalculator;
use Modules\Restaurant\Services\RecipeExploder;

/** @return array<int, array{yield: string, lines: list<array{item_id: int|null, sub_recipe_id: int|null, quantity: string, waste_percent: string}>}> */
function rcBook(): array
{
    return [
        1 => ['yield' => '1', 'lines' => [
            ['item_id' => 10, 'sub_recipe_id' => null, 'quantity' => '0.2', 'waste_percent' => '0'],
            ['item_id' => 11, 'sub_recipe_id' => null, 'quantity' => '0.05', 'waste_percent' => '10'],
            ['item_id' => null, 'sub_recipe_id' => 2, 'quantity' => '0.03', 'waste_percent' => '0'],
        ]],
        2 => ['yield' => '1', 'lines' => [
            ['item_id' => 12, 'sub_recipe_id' => null, 'quantity' => '0.2', 'waste_percent' => '0'],
            ['item_id' => 13, 'sub_recipe_id' => null, 'quantity' => '0.3', 'waste_percent' => '0'],
        ]],
    ];
}

it('uses the net quantity grossed up for waste, and a fraction of a sub-recipe', function (): void {
    $each = (new RecipeExploder)->explode(rcBook(), 1);

    expect($each)->toBe([10 => '0.2000', 11 => '0.0556', 12 => '0.0060', 13 => '0.0090']);
    expect((new RecipeExploder)->explode(rcBook(), 1, '10'))->toBe([10 => '2.0000', 11 => '0.5556', 12 => '0.0600', 13 => '0.0900']);
});

it('scales a sub-recipe by its yield', function (): void {
    $book = rcBook();
    $book[2]['yield'] = '2';

    expect((new RecipeExploder)->explode($book, 1))->toBe([10 => '0.2000', 11 => '0.0556', 12 => '0.0030', 13 => '0.0045']);
});

it('adds the same ingredient used twice and ignores a missing recipe', function (): void {
    $book = [1 => ['yield' => '1', 'lines' => [['item_id' => 10, 'sub_recipe_id' => null, 'quantity' => '1', 'waste_percent' => '0'], ['item_id' => 10, 'sub_recipe_id' => null, 'quantity' => '0.5', 'waste_percent' => '50']]]];

    expect((new RecipeExploder)->explode($book, 1))->toBe([10 => '2.0000'])->and((new RecipeExploder)->explode($book, 99))->toBe([]);
});

it('refuses a sub-recipe that contains itself, however deep, and a waste of 100%', function (): void {
    $loop = [1 => ['yield' => '1', 'lines' => [['item_id' => null, 'sub_recipe_id' => 2, 'quantity' => '1', 'waste_percent' => '0']]], 2 => ['yield' => '1', 'lines' => [['item_id' => null, 'sub_recipe_id' => 1, 'quantity' => '1', 'waste_percent' => '0']]]];

    expect(fn () => (new RecipeExploder)->explode($loop, 1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new RecipeExploder)->explode([1 => ['yield' => '1', 'lines' => [['item_id' => 10, 'sub_recipe_id' => null, 'quantity' => '1', 'waste_percent' => '100']]]], 1))->toThrow(InvalidArgumentException::class);
});

it('adds a plate up and rounds once, and derives the percent and the suggested price', function (): void {
    $calculator = new PlateCostCalculator;
    $cost = $calculator->cost(['10' => '0.2000', '11' => '0.0556', '99' => '1'], ['10' => '800', '11' => '70']);

    expect($cost)->toBe('163.89')->and($calculator->percent('163.89', '450'))->toBe('36.42')->and($calculator->percent('10', '0'))->toBeNull()
        ->and($calculator->suggestedPrice('163.89', '30'))->toBe('546.30')->and($calculator->suggestedPrice('100', '0'))->toBeNull();
});
