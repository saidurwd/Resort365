<?php

namespace Modules\Restaurant\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * Whether an outlet sells an item at a moment, and for how much (ARCHITECTURE §5.10.2 menu schedules),
 * without the database. An item with no schedules is sold whenever the outlet sells it. Otherwise it
 * is on sale while one of its schedules is: on a listed weekday between start and end, local time; a
 * window that ends before it starts (22:00–02:00) runs past midnight and belongs to the day it
 * started. The price is the outlet price adjusted by that schedule's % (the lowest, if several are
 * running), rounded half-up to 2 decimals. Sold out (86) is checked separately.
 *
 * A schedule is array{id: int, days: list<string>, start: string, end: string, adjustment: string, active: bool};
 * days are mon … sun, times H:i.
 */
class MenuAvailability
{
    private const array DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * @param  list<int>  $itemScheduleIds
     * @param  array<int, array{id: int, days: list<string>, start: string, end: string, adjustment: string, active: bool}>  $schedules  by id
     */
    public function isOnSale(array $itemScheduleIds, array $schedules, CarbonImmutable $at): bool
    {
        return $itemScheduleIds === [] || $this->running($itemScheduleIds, $schedules, $at) !== [];
    }

    /**
     * @param  list<int>  $itemScheduleIds
     * @param  array<int, array{id: int, days: list<string>, start: string, end: string, adjustment: string, active: bool}>  $schedules
     * @return string|null the price now, or null when the item is not on sale now
     */
    public function price(string $outletPrice, array $itemScheduleIds, array $schedules, CarbonImmutable $at): ?string
    {
        if ($itemScheduleIds === []) {
            return (string) BigDecimal::of($outletPrice)->toScale(2);
        }

        $running = $this->running($itemScheduleIds, $schedules, $at);

        if ($running === []) {
            return null;
        }

        $prices = array_map(fn (array $schedule): BigDecimal => BigDecimal::of($outletPrice)
            ->multipliedBy(BigDecimal::of(100)->plus($schedule['adjustment']))->dividedBy(100, 2, RoundingMode::HalfUp), $running);

        return (string) BigDecimal::min(...$prices);
    }

    /**
     * Whether one schedule is running at a moment.
     *
     * @param  array{id: int, days: list<string>, start: string, end: string, adjustment: string, active: bool}  $schedule
     */
    public function isRunning(array $schedule, CarbonImmutable $at): bool
    {
        if (! $schedule['active']) {
            return false;
        }

        $time = $at->format('H:i');
        $today = self::DAYS[$at->dayOfWeekIso - 1];
        $yesterday = self::DAYS[($at->dayOfWeekIso + 5) % 7];
        [$start, $end] = [substr($schedule['start'], 0, 5), substr($schedule['end'], 0, 5)];

        if ($start <= $end) {
            return in_array($today, $schedule['days'], true) && $time >= $start && $time < $end;
        }

        return (in_array($today, $schedule['days'], true) && $time >= $start) || (in_array($yesterday, $schedule['days'], true) && $time < $end);
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, array{id: int, days: list<string>, start: string, end: string, adjustment: string, active: bool}>  $schedules
     * @return list<array{id: int, days: list<string>, start: string, end: string, adjustment: string, active: bool}>
     */
    private function running(array $ids, array $schedules, CarbonImmutable $at): array
    {
        return array_values(array_filter(array_map(fn (int $id): ?array => $schedules[$id] ?? null, $ids),
            fn (?array $schedule): bool => $schedule !== null && $this->isRunning($schedule, $at)));
    }
}
