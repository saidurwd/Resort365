<?php

namespace Modules\Rates\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Rates\Actions\SaveRateSheet;
use Modules\Rates\Http\Controllers\Concerns\UsesCurrentProperty;
use Modules\Rates\Http\Requests\SaveRateSheetRequest;
use Modules\Rates\Models\Rate;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Models\Season;
use Modules\Rates\Support\DaysOfWeek;

/**
 * The rate sheet of a plan for one season (?season=id) or the base rates: every room and
 * cottage type with an every-day and a weekend rate.
 */
class RateSheetController extends Controller
{
    use UsesCurrentProperty;

    public function edit(Request $request, RatePlan $ratePlan, InventoryCatalog $catalog): View
    {
        Gate::authorize('viewRates', $ratePlan);

        $seasons = Season::query()->where('property_id', $ratePlan->property_id)->orderByDesc('priority')->orderBy('name')->get();
        $season = $this->season($request, $seasons);
        $weekend = DaysOfWeek::mask($this->weekendDays($ratePlan->property_id) ?: [5, 6]);

        $rates = Rate::query()->where('rate_plan_id', $ratePlan->id)->where('season_id', $season?->id)->get()
            ->groupBy(fn (Rate $rate): string => $rate->rateable_type->value.':'.$rate->rateable_id);

        return view('rates::rate-plans.rates', [
            'plan' => $ratePlan,
            'seasons' => $seasons,
            'season' => $season,
            'units' => $catalog->unitTypes($ratePlan->property_id),
            'values' => fn (string $key, string $set): ?Rate => ($rates[$key] ?? collect())->firstWhere('dow_mask', $set === 'every' ? DaysOfWeek::EVERY_DAY : $weekend),
            'weekendLabel' => DaysOfWeek::label($weekend),
            'currency' => $this->currency($ratePlan->property_id),
        ]);
    }

    public function update(SaveRateSheetRequest $request, RatePlan $ratePlan, SaveRateSheet $save): RedirectResponse
    {
        $seasons = Season::query()->where('property_id', $ratePlan->property_id)->get();
        $season = $this->season($request, $seasons);
        $weekend = DaysOfWeek::mask($this->weekendDays($ratePlan->property_id) ?: [5, 6]);

        /** @var array<string, array<string, array{amount?: string|null, extra_adult_amount?: string|null, extra_child_amount?: string|null}>> $rows */
        $rows = (array) $request->validated('rates', []);
        $save->handle($ratePlan, $season, $rows, $weekend);

        return to_route('rates.rate-plans.rates.edit', ['rate_plan' => $ratePlan, 'season' => $season?->id])
            ->with('success', __('Rates of ":plan" saved for :season.', ['plan' => $ratePlan->name, 'season' => $season->name ?? __('base rates')]));
    }

    /**
     * @param  Collection<int, Season>  $seasons
     */
    private function season(Request $request, $seasons): ?Season
    {
        $id = $request->integer('season');

        return $id > 0 ? ($seasons->firstWhere('id', $id) ?? abort(404)) : null;
    }
}
