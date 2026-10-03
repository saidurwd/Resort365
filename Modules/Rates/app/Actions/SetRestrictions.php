<?php

namespace Modules\Rates\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Modules\Property\DTOs\UnitTypeSummary;
use Modules\Rates\DTOs\RestrictionSet;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Models\RateRestriction;
use Modules\Rates\Support\DaysOfWeek;

/**
 * Replaces the restrictions of one scope (a rate plan or all plans; chosen types or all types)
 * on the matching days of a date range (validated by SetRestrictionsRequest). An empty set
 * clears them. Rows of other scopes are kept and combine with these (RateCalendar).
 */
class SetRestrictions extends Action
{
    /**
     * @param  list<UnitTypeSummary>|null  $units  null = every type
     * @return int dates changed
     */
    public function handle(int $propertyId, ?RatePlan $plan, ?array $units, CarbonImmutable $from, CarbonImmutable $to, int $daysMask, RestrictionSet $set): int
    {
        $dates = array_values(array_filter(
            array_map(fn (CarbonInterface $date): string => $date->toDateString(), CarbonPeriod::create($from, $to)->toArray()),
            fn (string $date): bool => DaysOfWeek::includes($daysMask, CarbonImmutable::parse($date)),
        ));
        $scopes = $units ?? [null];

        return $this->transaction(function () use ($propertyId, $plan, $scopes, $dates, $set): int {
            $tenantId = app(TenantContext::class)->tenantOrFail()->id;
            $rows = [];

            foreach ($scopes as $unit) {
                RateRestriction::query()->where('property_id', $propertyId)
                    ->where('rate_plan_id', $plan?->id)
                    ->where('rateable_type', $unit?->kind->value)->where('rateable_id', $unit?->id)
                    ->whereIn('date', $dates)->delete();

                if ($set->isEmpty()) {
                    continue;
                }

                foreach ($dates as $date) {
                    $rows[] = [
                        'tenant_id' => $tenantId, 'property_id' => $propertyId, 'rate_plan_id' => $plan?->id,
                        'rateable_type' => $unit?->kind->value, 'rateable_id' => $unit?->id, 'date' => $date,
                        'min_stay' => $set->minStay, 'max_stay' => $set->maxStay, 'closed_to_arrival' => $set->closedToArrival,
                        'closed_to_departure' => $set->closedToDeparture, 'stop_sell' => $set->stopSell, 'created_at' => now(), 'updated_at' => now(),
                    ];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                RateRestriction::query()->insert($chunk);
            }

            $logger = activity();

            if ($plan instanceof RatePlan) {
                $logger->performedOn($plan);
            }

            $logger->event('updated')->withProperties(['attributes' => [
                'restrictions' => ($set->isEmpty() ? __('cleared') : json_encode($set->toArray())).' · '
                    .($scopes === [null] ? __('all types') : implode(', ', array_map(fn (?UnitTypeSummary $unit): string => (string) $unit?->name, $scopes)))
                    .' · '.($dates[0] ?? '').' – '.($dates[count($dates) - 1] ?? ''),
            ]])->log('Restrictions changed');

            return count($dates);
        });
    }
}
