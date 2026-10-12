<?php

namespace Modules\Restaurant\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Turns a recipe into the raw ingredients of its portions (Step 5.7), without the database. A line's quantity is the
 * net amount in the plate; its waste percent is yield loss (10% waste on 90 g means 100 g is used). A sub-recipe line
 * is a quantity of the sub-recipe's yield, so it uses that fraction of the sub-recipe's own ingredients.
 */
class RecipeExploder
{
    private const int MAX_DEPTH = 8;

    /**
     * @param  array<int, array{yield: string, lines: list<array{item_id: int|null, sub_recipe_id: int|null, quantity: string, waste_percent: string}>}>  $recipes  by recipe id
     * @return array<int, string> item id => quantity (four decimals)
     *
     * @throws InvalidArgumentException a sub-recipe that contains itself, or is nested too deep
     */
    public function explode(array $recipes, int $recipeId, string $portions = '1'): array
    {
        $totals = [];
        $this->walk($recipes, $recipeId, BigDecimal::of($portions), [], $totals);

        return array_map(fn (BigDecimal $quantity): string => (string) $quantity->toScale(4, RoundingMode::HalfUp), $totals);
    }

    /**
     * @param  array<int, array{yield: string, lines: list<array{item_id: int|null, sub_recipe_id: int|null, quantity: string, waste_percent: string}>}>  $recipes
     * @param  list<int>  $path
     * @param  array<int, BigDecimal>  $totals
     */
    private function walk(array $recipes, int $recipeId, BigDecimal $factor, array $path, array &$totals): void
    {
        if (in_array($recipeId, $path, true) || count($path) >= self::MAX_DEPTH) {
            throw new InvalidArgumentException('A sub-recipe cannot contain itself, and sub-recipes nest at most '.self::MAX_DEPTH.' deep.');
        }

        $recipe = $recipes[$recipeId] ?? null;

        if ($recipe === null) {
            return;
        }

        foreach ($recipe['lines'] as $line) {
            $gross = $this->gross($line['quantity'], $line['waste_percent'])->multipliedBy($factor);

            if ($line['item_id'] !== null) {
                $totals[$line['item_id']] = ($totals[$line['item_id']] ?? BigDecimal::zero())->plus($gross);
            } elseif ($line['sub_recipe_id'] !== null) {
                $yield = BigDecimal::of($recipes[$line['sub_recipe_id']]['yield'] ?? '1');
                $this->walk($recipes, $line['sub_recipe_id'], $yield->isPositive() ? $gross->dividedBy($yield, 12, RoundingMode::HalfUp) : BigDecimal::zero(), [...$path, $recipeId], $totals);
            }
        }
    }

    private function gross(string $quantity, string $wastePercent): BigDecimal
    {
        $keep = BigDecimal::of('100')->minus($wastePercent);

        if (! $keep->isPositive()) {
            throw new InvalidArgumentException('Waste must be below 100%.');
        }

        return BigDecimal::of($quantity)->multipliedBy('100')->dividedBy($keep, 12, RoundingMode::HalfUp);
    }
}
