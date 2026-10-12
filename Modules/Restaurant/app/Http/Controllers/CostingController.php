<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\Settings;
use Modules\Restaurant\Http\Controllers\Concerns\CurrentProperty;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\Recipe;
use Modules\Restaurant\Services\FoodCosting;

/**
 * Restaurant → Food costing: every priced dish with its plate cost, food cost % and the price that would give the target.
 */
class CostingController extends Controller
{
    use CurrentProperty;

    public function index(Request $request, FoodCosting $costing, Settings $settings): View
    {
        Gate::authorize('viewAny', Recipe::class);
        $propertyId = $this->propertyId();
        $outletId = $request->integer('outlet') ?: null;

        return view('restaurant::recipes.costing', [
            'rows' => $costing->rows($propertyId, $outletId), 'outlets' => Outlet::query()->where('property_id', $propertyId)->orderBy('name')->pluck('name', 'id')->all(), 'outletId' => $outletId,
            'target' => (string) $settings->get('restaurant.target_food_cost_percent', $propertyId),
        ]);
    }
}
