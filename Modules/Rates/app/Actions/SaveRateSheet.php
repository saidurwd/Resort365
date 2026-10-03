<?php

namespace Modules\Rates\Actions;

use App\Support\Actions\Action;
use Modules\Property\Enums\UnitKind;
use Modules\Rates\Models\Rate;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Models\Season;
use Modules\Rates\Support\DaysOfWeek;

/**
 * Saves the rate sheet of a plan for one season (or the base rates): per room or cottage type,
 * an "every day" rate and an optional "weekend" rate (validated by SaveRateSheetRequest).
 * An empty amount removes that rate. Keeps one rate per plan, type, season and day set.
 */
class SaveRateSheet extends Action
{
    /**
     * @param  array<string, array<string, array{amount?: string|null, extra_adult_amount?: string|null, extra_child_amount?: string|null}>>  $rows
     *                                                                                                                                               unit key ("room_type:4") => ["every" | "weekend" => amounts]
     * @param  int  $weekendMask  the property's weekend days (DaysOfWeek)
     */
    public function handle(RatePlan $plan, ?Season $season, array $rows, int $weekendMask): void
    {
        $this->transaction(function () use ($plan, $season, $rows, $weekendMask): void {
            foreach ($rows as $key => $sets) {
                [$type, $id] = explode(':', $key);

                foreach (['every' => DaysOfWeek::EVERY_DAY, 'weekend' => $weekendMask] as $set => $mask) {
                    $this->save($plan, $season, UnitKind::from($type), (int) $id, $mask, $sets[$set] ?? []);
                }
            }
        });
    }

    /**
     * @param  array{amount?: string|null, extra_adult_amount?: string|null, extra_child_amount?: string|null}  $values
     */
    private function save(RatePlan $plan, ?Season $season, UnitKind $type, int $id, int $mask, array $values): void
    {
        $existing = Rate::query()->where('rate_plan_id', $plan->id)->where('rateable_type', $type->value)->where('rateable_id', $id)
            ->where('season_id', $season?->id)->where('dow_mask', $mask)->get();
        $amount = $values['amount'] ?? null;

        if ($amount === null || $amount === '') {
            $existing->each(fn (Rate $rate) => $rate->delete());

            return;
        }

        $rate = $existing->shift() ?? new Rate([
            'property_id' => $plan->property_id, 'rate_plan_id' => $plan->id, 'rateable_type' => $type,
            'rateable_id' => $id, 'season_id' => $season?->id, 'dow_mask' => $mask,
        ]);
        $existing->each(fn (Rate $duplicate) => $duplicate->delete());

        $rate->fill([
            'amount' => $amount,
            'extra_adult_amount' => ($values['extra_adult_amount'] ?? null) ?: '0',
            'extra_child_amount' => ($values['extra_child_amount'] ?? null) ?: '0',
        ])->save();
    }
}
