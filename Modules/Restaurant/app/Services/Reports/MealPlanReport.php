<?php

namespace Modules\Restaurant\Services\Reports;

/**
 * Meals included in guests' plans against meals taken (ARCHITECTURE §5.10.9 step 5, §5.10.14), without the
 * database: per date and meal period, the covers the plans included, the covers redeemed, those not taken
 * and those taken beyond the plan.
 */
class MealPlanReport
{
    /**
     * @param  array<string, array<string, int>>  $included  date => period => covers
     * @param  array<string, array<string, int>>  $taken  date => period => covers
     * @return list<array{date: string, period: string, included: int, taken: int, not_taken: int, over: int}> by date, then breakfast, lunch, dinner
     */
    public function compare(array $included, array $taken): array
    {
        $order = ['breakfast' => 1, 'lunch' => 2, 'dinner' => 3];
        $rows = [];

        foreach (array_unique([...array_keys($included), ...array_keys($taken)]) as $date) {
            foreach (array_unique([...array_keys($included[$date] ?? []), ...array_keys($taken[$date] ?? [])]) as $period) {
                $in = $included[$date][$period] ?? 0;
                $out = $taken[$date][$period] ?? 0;
                $rows[] = ['date' => $date, 'period' => $period, 'included' => $in, 'taken' => $out, 'not_taken' => max(0, $in - $out), 'over' => max(0, $out - $in)];
            }
        }

        usort($rows, fn (array $a, array $b): int => [$a['date'], $order[$a['period']] ?? 9] <=> [$b['date'], $order[$b['period']] ?? 9]);

        return $rows;
    }
}
