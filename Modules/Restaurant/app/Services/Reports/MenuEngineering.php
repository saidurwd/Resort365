<?php

namespace Modules\Restaurant\Services\Reports;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Restaurant\Enums\MenuClass;

/**
 * Menu engineering (Step 7.2): every dish is classed by its popularity and the margin it earns per portion. Pure.
 *
 * - popularity is a dish's share of all portions sold; it is "popular" when that share is at least 70% of an equal share
 *   (the 70% rule: with 10 dishes the cut is 7%);
 * - the margin is the price a portion brings (net of discounts) less what its ingredients cost; a dish earns a "high" margin when
 *   that is at least the average margin of all portions sold (total margin ÷ portions);
 * - star: popular and high margin; plowhorse: popular, low margin; puzzle: not popular, high margin; dog: neither.
 *
 * A dish whose cost is not known (no recipe) cannot be placed, so it is returned apart and does not count in the averages.
 */
class MenuEngineering
{
    /**
     * @param  list<array{id: int, name: string, category: string, quantity: string, revenue: string, cost: string|null}>  $dishes  portions sold, net revenue and food cost
     * @return array{
     *     dishes: list<array{id: int, name: string, category: string, quantity: string, price: string, cost: string, margin: string, popularity: string, revenue: string, class: MenuClass}>,
     *     unknown: list<array{id: int, name: string, category: string, quantity: string, revenue: string}>,
     *     popularity_cut: string,
     *     margin_cut: string
     * }
     */
    public function classify(array $dishes): array
    {
        $known = array_values(array_filter($dishes, fn (array $dish): bool => $dish['cost'] !== null && BigDecimal::of($dish['quantity'])->isPositive()));
        $unknown = array_values(array_map(
            fn (array $dish): array => ['id' => $dish['id'], 'name' => $dish['name'], 'category' => $dish['category'], 'quantity' => $dish['quantity'], 'revenue' => $dish['revenue']],
            array_filter($dishes, fn (array $dish): bool => $dish['cost'] === null && BigDecimal::of($dish['quantity'])->isPositive()),
        ));

        if ($known === []) {
            return ['dishes' => [], 'unknown' => $unknown, 'popularity_cut' => '0.0', 'margin_cut' => '0.00'];
        }

        $portions = array_reduce($known, fn (BigDecimal $sum, array $dish): BigDecimal => $sum->plus($dish['quantity']), BigDecimal::zero());
        $revenue = array_reduce($known, fn (BigDecimal $sum, array $dish): BigDecimal => $sum->plus($dish['revenue']), BigDecimal::zero());
        $cost = array_reduce($known, fn (BigDecimal $sum, array $dish): BigDecimal => $sum->plus((string) $dish['cost']), BigDecimal::zero());
        $popularityCut = BigDecimal::of(100)->dividedBy(count($known), 10, RoundingMode::HalfUp)->multipliedBy('0.7');
        $marginCut = $revenue->minus($cost)->dividedBy($portions, 10, RoundingMode::HalfUp);
        $out = [];

        foreach ($known as $dish) {
            $quantity = BigDecimal::of($dish['quantity']);
            $price = BigDecimal::of($dish['revenue'])->dividedBy($quantity, 10, RoundingMode::HalfUp);
            $unitCost = BigDecimal::of((string) $dish['cost'])->dividedBy($quantity, 10, RoundingMode::HalfUp);
            $margin = $price->minus($unitCost);
            $popularity = $quantity->multipliedBy(100)->dividedBy($portions, 10, RoundingMode::HalfUp);
            $popular = $popularity->isGreaterThanOrEqualTo($popularityCut);
            $high = $margin->isGreaterThanOrEqualTo($marginCut);

            $out[] = [
                'id' => $dish['id'], 'name' => $dish['name'], 'category' => $dish['category'], 'quantity' => str_contains($dish['quantity'], '.') ? rtrim(rtrim($dish['quantity'], '0'), '.') : $dish['quantity'],
                'price' => (string) $price->toScale(2, RoundingMode::HalfUp), 'cost' => (string) $unitCost->toScale(2, RoundingMode::HalfUp), 'margin' => (string) $margin->toScale(2, RoundingMode::HalfUp),
                'popularity' => (string) $popularity->toScale(1, RoundingMode::HalfUp), 'revenue' => (string) BigDecimal::of($dish['revenue'])->toScale(2, RoundingMode::HalfUp),
                'class' => match (true) {
                    $popular && $high => MenuClass::Star,
                    $popular => MenuClass::Plowhorse,
                    $high => MenuClass::Puzzle,
                    default => MenuClass::Dog,
                },
            ];
        }

        usort($out, fn (array $a, array $b): int => [array_search($a['class'], MenuClass::cases(), true), -(float) $a['quantity']] <=> [array_search($b['class'], MenuClass::cases(), true), -(float) $b['quantity']]);

        return ['dishes' => $out, 'unknown' => $unknown, 'popularity_cut' => (string) $popularityCut->toScale(1, RoundingMode::HalfUp), 'margin_cut' => (string) $marginCut->toScale(2, RoundingMode::HalfUp)];
    }
}
