<?php

namespace Modules\Rates\Services;

use Carbon\CarbonInterface;
use Modules\Rates\DTOs\NightlyRate;
use Modules\Rates\DTOs\RateRow;
use Modules\Rates\DTOs\SeasonWindow;
use Modules\Rates\Enums\RateSource;
use Modules\Rates\Support\DaysOfWeek;

/**
 * The price of one night (ARCHITECTURE §6.3), without database access:
 *
 * 1. a date override, if any (extras still come from the rate below);
 * 2. else the rate of the highest-priority season covering the date;
 * 3. else the base rate (no season).
 *
 * Among the rates of a season (or the base), only those whose days include the weekday count,
 * and the most specific wins: a Fri–Sat weekend rate beats an every-day rate. A season without a
 * rate for this type falls back to the base rate.
 */
class NightlyRateResolver
{
    /**
     * @param  list<SeasonWindow>  $seasons
     * @param  list<RateRow>  $rates  the plan's rates for one room or cottage type
     * @param  string|null  $override  the date's override amount
     */
    public function resolve(CarbonInterface $date, array $seasons, array $rates, ?string $override = null): ?NightlyRate
    {
        $day = $date->format('Y-m-d');
        $season = $this->seasonOn($day, $seasons);
        $row = ($season instanceof SeasonWindow ? $this->pick($rates, $season->id, $date) : null) ?? $this->pick($rates, null, $date);

        if ($override !== null) {
            return new NightlyRate($day, $override, $row->extraAdultAmount ?? '0.00', $row->extraChildAmount ?? '0.00', RateSource::Override,
                $season?->id, $season?->name, $season?->color);
        }

        if (! $row instanceof RateRow) {
            return null;
        }

        $fromSeason = $row->seasonId !== null;

        return new NightlyRate($day, $row->amount, $row->extraAdultAmount, $row->extraChildAmount, $fromSeason ? RateSource::Season : RateSource::Base,
            $fromSeason ? $season?->id : null, $fromSeason ? $season?->name : null, $fromSeason ? $season?->color : null);
    }

    /**
     * The highest-priority season covering the date (ties: the most recently created).
     *
     * @param  list<SeasonWindow>  $seasons
     */
    public function seasonOn(string $date, array $seasons): ?SeasonWindow
    {
        $best = null;

        foreach ($seasons as $season) {
            if ($season->covers($date) && (! $best instanceof SeasonWindow || $season->priority > $best->priority
                || ($season->priority === $best->priority && $season->id > $best->id))) {
                $best = $season;
            }
        }

        return $best;
    }

    /**
     * @param  list<RateRow>  $rates
     */
    private function pick(array $rates, ?int $seasonId, CarbonInterface $date): ?RateRow
    {
        $best = null;

        foreach ($rates as $rate) {
            if ($rate->seasonId !== $seasonId || ! DaysOfWeek::includes($rate->dowMask, $date)) {
                continue;
            }

            if (! $best instanceof RateRow || DaysOfWeek::count($rate->dowMask) < DaysOfWeek::count($best->dowMask)
                || (DaysOfWeek::count($rate->dowMask) === DaysOfWeek::count($best->dowMask) && $rate->id > $best->id)) {
                $best = $rate;
            }
        }

        return $best;
    }
}
