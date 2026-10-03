<?php

namespace Modules\Rates\Http\Requests\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Modules\Property\DTOs\UnitTypeSummary;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Support\DaysOfWeek;

/**
 * The shared part of the rate grid's bulk forms: types, date range (at most a year) and weekdays.
 */
trait GridRangeRules
{
    use UsesCurrentProperty;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rangeRules(): array
    {
        return [
            'units' => ['required', 'array', 'min:1'],
            'units.*' => ['string'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.$this->latestEnd()],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', 'between:1,7'],
        ];
    }

    protected function validateUnits(Validator $validator): void
    {
        $plan = $this->route('rate_plan');
        $known = $plan instanceof RatePlan ? $this->unitTypes($plan->property_id) : [];

        if (array_diff((array) $this->input('units', []), ['*', ...array_keys($known)]) !== []) {
            $validator->errors()->add('units', __('Unknown room or cottage type.'));
        }
    }

    /**
     * The chosen types ("*" = all of the property's types).
     *
     * @return list<UnitTypeSummary>
     */
    public function units(): array
    {
        $plan = $this->route('rate_plan');
        $known = $plan instanceof RatePlan ? $this->unitTypes($plan->property_id) : [];
        $chosen = (array) $this->input('units', []);

        return in_array('*', $chosen, true) ? array_values($known) : array_values(array_intersect_key($known, array_flip($chosen)));
    }

    public function from(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->input('from'));
    }

    public function to(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->input('to'));
    }

    public function daysMask(): int
    {
        return DaysOfWeek::mask(array_map(intval(...), (array) $this->input('days', [])));
    }

    private function latestEnd(): string
    {
        $from = $this->input('from');

        return is_string($from) && strtotime($from) !== false ? CarbonImmutable::parse($from)->addYear()->toDateString() : '2999-12-31';
    }
}
