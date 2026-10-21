<?php

/*
| Menu engineering (Step 7.2): dishes classed by popularity (the 70% rule) and margin (against the average margin of all portions
| sold). Pure.
*/
use Modules\Restaurant\Enums\MenuClass;
use Modules\Restaurant\Services\Reports\MenuEngineering;
use Tests\TestCase;

// The labels and advice go through the translator, so the application is booted (no database).
uses(TestCase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array{id: int, name: string, category: string, quantity: string, revenue: string, cost: string|null}
 */
function meDish(int $id, string $name, string $quantity, string $revenue, ?string $cost, array $overrides = []): array
{
    return ['id' => $id, 'name' => $name, 'category' => 'Mains', 'quantity' => $quantity, 'revenue' => $revenue, 'cost' => $cost, ...$overrides];
}

it('puts a dish in each class by its popularity and margin', function (): void {
    // 21 portions, revenue 10,350, cost 5,576: the average margin is 227.33 and a popular dish has at least 17.5% of the portions (4 dishes: 25% × 70%).
    $result = (new MenuEngineering)->classify([
        meDish(1, 'Burger', '10', '4500.00', '2096.00'), meDish(2, 'Prawns', '8', '4800.00', '3200.00'), meDish(3, 'Salad', '2', '800.00', '80.00'), meDish(4, 'Pasta', '1', '250.00', '200.00'),
    ]);
    $byName = collect($result['dishes'])->mapWithKeys(fn (array $dish): array => [$dish['name'] => $dish['class']]);

    expect($result['popularity_cut'])->toBe('17.5')->and($result['margin_cut'])->toBe('227.33')
        ->and($byName->all())->toBe(['Burger' => MenuClass::Star, 'Prawns' => MenuClass::Plowhorse, 'Salad' => MenuClass::Puzzle, 'Pasta' => MenuClass::Dog])
        ->and($result['dishes'][0])->toMatchArray(['name' => 'Burger', 'quantity' => '10', 'price' => '450.00', 'cost' => '209.60', 'margin' => '240.40', 'popularity' => '47.6', 'revenue' => '4500.00']);
});

it('lists the dishes in class order, the most sold first', function (): void {
    $result = (new MenuEngineering)->classify([
        meDish(1, 'Pasta', '1', '250.00', '200.00'), meDish(2, 'Salad', '2', '800.00', '80.00'), meDish(3, 'Prawns', '8', '4800.00', '3200.00'), meDish(4, 'Burger', '10', '4500.00', '2096.00'),
    ]);

    expect(array_column($result['dishes'], 'name'))->toBe(['Burger', 'Prawns', 'Salad', 'Pasta']);
});

it('counts a dish exactly on a cut as high', function (): void {
    // Two dishes sell 50 and 50: each has 50%, the cut is 35%; both earn exactly the average margin of 100, so both are stars.
    $result = (new MenuEngineering)->classify([meDish(1, 'A', '50', '7500.00', '2500.00'), meDish(2, 'B', '50', '7500.00', '2500.00')]);

    expect(array_column($result['dishes'], 'class'))->toBe([MenuClass::Star, MenuClass::Star]);
});

it('leaves a dish with no known cost out of the classes and the averages', function (): void {
    $result = (new MenuEngineering)->classify([meDish(1, 'Burger', '10', '4500.00', '2096.00'), meDish(2, 'Soup', '30', '3000.00', null), meDish(3, 'Nothing', '0', '0.00', '0.00')]);

    expect($result['dishes'])->toHaveCount(1)->and($result['unknown'])->toBe([['id' => 2, 'name' => 'Soup', 'category' => 'Mains', 'quantity' => '30', 'revenue' => '3000.00']])
        ->and($result['dishes'][0]['popularity'])->toBe('100.0')->and($result['dishes'][0]['class'])->toBe(MenuClass::Star);
});

it('answers nothing when there is nothing to class', function (): void {
    $result = (new MenuEngineering)->classify([]);

    expect($result)->toBe(['dishes' => [], 'unknown' => [], 'popularity_cut' => '0.0', 'margin_cut' => '0.00']);
});

it('has advice for every class', function (): void {
    foreach (MenuClass::cases() as $class) {
        expect($class->advice())->not->toBe('')->and($class->label())->not->toBe('');
    }
});
