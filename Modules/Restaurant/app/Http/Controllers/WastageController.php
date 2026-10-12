<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Restaurant\Actions\RecordWastage;
use Modules\Restaurant\DTOs\NewWastage;
use Modules\Restaurant\Enums\WastageReason;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Http\Controllers\Concerns\CurrentProperty;
use Modules\Restaurant\Http\Requests\WastageRequest;
use Modules\Restaurant\Models\WastageEntry;
use Modules\Restaurant\Services\Lookups\RecipeOptions;

/**
 * Restaurant → Wastage: food and ingredients written off, taken out of the store at cost.
 */
class WastageController extends Controller
{
    use CurrentProperty;

    public function index(RecipeOptions $options): View
    {
        Gate::authorize('viewAny', WastageEntry::class);
        $propertyId = $this->propertyId();

        return view('restaurant::wastage.index', [
            'entries' => WastageEntry::query()->where('property_id', $propertyId)->orderByDesc('id')->limit(100)->get(), 'stores' => $options->stores($propertyId),
            'canRecord' => auth()->user()?->can('restaurant.wastage.record') ?? false,
        ]);
    }

    public function create(RecipeOptions $options): View
    {
        Gate::authorize('create', WastageEntry::class);
        $propertyId = $this->propertyId();

        return view('restaurant::wastage.create', ['stores' => $options->stores($propertyId), 'items' => $options->items(), 'dishes' => $options->dishes($propertyId), 'reasons' => WastageReason::options()]);
    }

    public function store(WastageRequest $request, RecordWastage $record): RedirectResponse
    {
        Gate::authorize('create', WastageEntry::class);

        try {
            $entry = $record->handle(new NewWastage(
                (int) $request->validated('store_id'), WastageReason::from((string) $request->validated('reason')), (int) $request->user()?->getAuthIdentifier(), $request->items(), $request->dishes(),
                $request->filled('wasted_on') ? (string) $request->validated('wasted_on') : null, $request->filled('notes') ? (string) $request->validated('notes') : null,
            ));
        } catch (RestaurantSetupInvalid $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('restaurant.wastage.show', $entry)->with('success', __('Wastage :no written off.', ['no' => $entry->wastage_no]));
    }

    public function show(WastageEntry $entry, RecipeOptions $options, ItemCatalog $catalog, AuditTrail $audit): View
    {
        Gate::authorize('view', WastageEntry::class);
        $entry->load('lines');

        return view('restaurant::wastage.show', ['entry' => $entry, 'store' => $options->stores($entry->property_id)[$entry->store_id] ?? null, 'items' => $catalog->many($entry->lines->pluck('item_id')->all()), 'trail' => $audit->for($entry)]);
    }
}
