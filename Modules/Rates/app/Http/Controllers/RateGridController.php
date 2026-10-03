<?php

namespace Modules\Rates\Http\Controllers;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Rates\Actions\SetRateOverrides;
use Modules\Rates\Actions\SetRestrictions;
use Modules\Rates\Http\Controllers\Concerns\UsesCurrentProperty;
use Modules\Rates\Http\Requests\SetRateOverridesRequest;
use Modules\Rates\Http\Requests\SetRestrictionsRequest;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Services\RateCalendar;

/**
 * The rate grid (ARCHITECTURE §5.5): room and cottage types × 14 nights for one rate plan, with
 * bulk forms for date prices and restrictions.
 */
class RateGridController extends Controller
{
    use UsesCurrentProperty;

    public const int DAYS = 14;

    public function index(Request $request, InventoryCatalog $catalog, RateCalendar $calendar, PropertyDirectory $properties): View
    {
        $propertyId = $this->currentPropertyId();
        $plans = RatePlan::query()->where('property_id', $propertyId)->orderByDesc('is_active')->orderBy('sort_order')->orderBy('name')->get();
        $plan = $plans->firstWhere('id', $request->integer('plan')) ?? $plans->first();

        if ($plan instanceof RatePlan) {
            Gate::authorize('viewRates', $plan);
        } else {
            abort_unless($request->user()?->can('rates.rate.view'), 403);
        }

        $start = $this->start($request, $properties->find($propertyId)->businessDate ?? now()->toDateString());
        $end = $start->addDays(self::DAYS - 1);
        $units = $catalog->unitTypes($propertyId, activeOnly: true);

        return view('rates::grid.index', [
            'propertyName' => $this->currentPropertyName(),
            'plans' => $plans,
            'plan' => $plan,
            'units' => $units,
            'dates' => array_map(fn (CarbonInterface $date): CarbonImmutable => CarbonImmutable::parse($date), CarbonPeriod::create($start, $end)->toArray()),
            'start' => $start,
            'rates' => $plan instanceof RatePlan ? $calendar->rates($plan, $units, $start, $end) : [],
            'restrictions' => $plan instanceof RatePlan ? $calendar->restrictions($plan, $units, $start, $end) : [],
            'seasons' => $calendar->seasons($propertyId, $start, $end),
            'weekend' => $this->weekendDays($propertyId),
            'currency' => $this->currency($propertyId),
        ]);
    }

    public function overrides(SetRateOverridesRequest $request, RatePlan $ratePlan, SetRateOverrides $set): RedirectResponse
    {
        $nights = $set->handle($ratePlan, $request->units(), $request->from(), $request->to(), $request->daysMask(), $request->amount());

        return $this->back($ratePlan, $request->from())->with('success', $request->amount() === null
            ? trans_choice(':count date price cleared.|:count date prices cleared.', $nights)
            : trans_choice('Price set for :count night.|Price set for :count nights.', $nights));
    }

    public function restrictions(SetRestrictionsRequest $request, RatePlan $ratePlan, SetRestrictions $set): RedirectResponse
    {
        $set->handle($ratePlan->property_id, $request->boolean('all_plans') ? null : $ratePlan, $request->allUnits() ? null : $request->units(),
            $request->from(), $request->to(), $request->daysMask(), $request->restrictions());

        return $this->back($ratePlan, $request->from())->with('success', $request->restrictions()->isEmpty() ? __('Restrictions cleared.') : __('Restrictions saved.'));
    }

    private function start(Request $request, string $default): CarbonImmutable
    {
        $start = $request->query('start');

        return is_string($start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) === 1 && strtotime($start) !== false
            ? CarbonImmutable::parse($start)
            : CarbonImmutable::parse($default);
    }

    private function back(RatePlan $plan, CarbonImmutable $start): RedirectResponse
    {
        return to_route('rates.grid', ['plan' => $plan->id, 'start' => $start->toDateString()]);
    }
}
