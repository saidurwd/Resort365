<?php

namespace Modules\Rates\Actions;

use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Modules\Property\DTOs\UnitTypeSummary;
use Modules\Rates\Models\RateOverride;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Support\DaysOfWeek;

/**
 * Sets (or with a null amount clears) the price of a plan for chosen types on the matching days
 * of a date range (validated by SetRateOverridesRequest). Written in bulk, so the plan's audit
 * trail gets one entry for the whole change.
 */
class SetRateOverrides extends Action
{
    /**
     * @param  list<UnitTypeSummary>  $units
     * @return int nights changed
     */
    public function handle(RatePlan $plan, array $units, CarbonImmutable $from, CarbonImmutable $to, int $daysMask, ?string $amount): int
    {
        $dates = array_values(array_filter(
            array_map(fn (CarbonInterface $date): string => $date->toDateString(), CarbonPeriod::create($from, $to)->toArray()),
            fn (string $date): bool => DaysOfWeek::includes($daysMask, CarbonImmutable::parse($date)),
        ));

        return $this->transaction(function () use ($plan, $units, $dates, $amount): int {
            $changed = 0;

            foreach ($units as $unit) {
                $query = RateOverride::query()->where('rate_plan_id', $plan->id)
                    ->where('rateable_type', $unit->kind->value)->where('rateable_id', $unit->id)->whereIn('date', $dates);

                if ($amount === null) {
                    $changed += $query->delete();

                    continue;
                }

                $now = now();
                RateOverride::query()->upsert(array_map(fn (string $date): array => [
                    'tenant_id' => $plan->tenant_id, 'property_id' => $plan->property_id, 'rate_plan_id' => $plan->id,
                    'rateable_type' => $unit->kind->value, 'rateable_id' => $unit->id, 'date' => $date, 'amount' => $amount,
                    'created_at' => $now, 'updated_at' => $now,
                ], $dates), ['rate_plan_id', 'rateable_type', 'rateable_id', 'date'], ['amount', 'updated_at']);
                $changed += count($dates);
            }

            if ($changed > 0) {
                activity()->performedOn($plan)->event('updated')->withProperties(['attributes' => [
                    'date_prices' => ($amount ?? __('cleared')).' · '.implode(', ', array_map(fn (UnitTypeSummary $unit): string => $unit->name, $units))
                        .' · '.($dates[0] ?? '').' – '.($dates[count($dates) - 1] ?? ''),
                ]])->log('Date prices changed');
            }

            return $changed;
        });
    }
}
