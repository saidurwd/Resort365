<?php

namespace Modules\Rates\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Modules\Rates\DTOs\NightlyRate;
use Modules\Rates\DTOs\RateRow;
use Modules\Rates\DTOs\RestrictionSet;
use Modules\Rates\DTOs\SeasonWindow;
use Modules\Rates\Models\Rate;
use Modules\Rates\Models\RateOverride;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Models\RateRestriction;
use Modules\Rates\Models\Season;
use Modules\Rates\Models\SeasonPeriod;

/**
 * Loads a rate plan's seasons, rates, overrides and restrictions for a date range and resolves
 * them night by night (NightlyRateResolver). Used by the rate grid; pricing (Step 1.5) builds on it.
 */
class RateCalendar
{
    public function __construct(private readonly NightlyRateResolver $resolver) {}

    /**
     * Nightly rates per type and date (null = no price set).
     *
     * @param  list<string>  $unitKeys  e.g. "room_type:4"
     * @return array<string, array<string, NightlyRate|null>> unit key => date => rate
     */
    public function rates(RatePlan $plan, array $unitKeys, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $seasons = $this->seasons($plan->property_id, $from, $to);
        $rates = Rate::query()->where('rate_plan_id', $plan->id)->get()->groupBy(fn (Rate $rate): string => $rate->rateable_type->value.':'.$rate->rateable_id);
        $overrides = RateOverride::query()->where('rate_plan_id', $plan->id)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->get()
            ->groupBy(fn (RateOverride $override): string => $override->rateable_type->value.':'.$override->rateable_id);

        $grid = [];

        foreach ($unitKeys as $key) {
            $rows = ($rates[$key] ?? collect())->map(fn (Rate $rate): RateRow => new RateRow(
                $rate->id, $rate->season_id, $rate->dow_mask, $rate->amount, $rate->extra_adult_amount, $rate->extra_child_amount,
            ))->values()->all();
            $unitOverrides = ($overrides[$key] ?? collect())->mapWithKeys(fn (RateOverride $override): array => [$override->date->toDateString() => $override->amount])->all();

            foreach (CarbonPeriod::create($from, $to) as $date) {
                $grid[$key][$date->toDateString()] = $this->resolver->resolve($date, $seasons, $rows, $unitOverrides[$date->toDateString()] ?? null);
            }
        }

        return $grid;
    }

    /**
     * The strictest restrictions per type and date (rows for all plans or all types included).
     *
     * @param  list<string>  $unitKeys  e.g. "room_type:4"
     * @return array<string, array<string, RestrictionSet>> unit key => date => restrictions
     */
    public function restrictions(RatePlan $plan, array $unitKeys, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = RateRestriction::query()->where('property_id', $plan->property_id)
            ->where(fn ($query) => $query->whereNull('rate_plan_id')->orWhere('rate_plan_id', $plan->id))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])->get();

        $result = [];

        foreach ($unitKeys as $key) {
            foreach ($rows as $row) {
                if ($row->rateable_type !== null && $row->rateable_type->value.':'.$row->rateable_id !== $key) {
                    continue;
                }

                $date = $row->date->toDateString();
                $set = new RestrictionSet($row->min_stay, $row->max_stay, $row->closed_to_arrival, $row->closed_to_departure, $row->stop_sell);
                $result[$key][$date] = isset($result[$key][$date]) ? $result[$key][$date]->merge($set) : $set;
            }
        }

        return $result;
    }

    /**
     * Active seasons with periods overlapping the range.
     *
     * @return list<SeasonWindow>
     */
    public function seasons(int $propertyId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Season::query()->where('property_id', $propertyId)->where('is_active', true)
            ->with(['periods' => fn ($query) => $query->where('start_date', '<=', $to->toDateString())->where('end_date', '>=', $from->toDateString())])
            ->get()
            ->filter(fn (Season $season): bool => $season->periods->isNotEmpty())
            ->map(fn (Season $season): SeasonWindow => new SeasonWindow($season->id, $season->name, $season->color->value, $season->priority,
                $season->periods->map(fn (SeasonPeriod $period): array => [$period->start_date->toDateString(), $period->end_date->toDateString()])->values()->all()))
            ->values()->all();
    }
}
