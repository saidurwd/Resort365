<?php

namespace Modules\Procurement\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Inventory\Contracts\ReorderSuggestions;
use Modules\Procurement\Actions\CreateRequisitionFromSuggestions;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Http\Controllers\Concerns\ProcurementScreen;
use Modules\Procurement\Http\Requests\SuggestionRequest;
use Modules\Procurement\Models\PurchaseRequisition;
use Modules\Procurement\Services\Lookups;

/**
 * Procurement → Reorder suggestions: items at or below their reorder level, to turn into a purchase requisition.
 */
class SuggestionController extends Controller
{
    use ProcurementScreen;

    public function index(ReorderSuggestions $suggestions, Lookups $lookups): View
    {
        Gate::authorize('viewAny', PurchaseRequisition::class);

        return view('procurement::suggestions.index', [
            'suggestions' => collect($suggestions->forProperty($this->propertyId()))->groupBy('storeName'), 'departments' => $lookups->departments(),
            'canCreate' => auth()->user()?->can('procurement.requisition.create') ?? false, 'subtitle' => $this->propertyName(),
        ]);
    }

    public function store(SuggestionRequest $request, CreateRequisitionFromSuggestions $create): RedirectResponse
    {
        Gate::authorize('create', PurchaseRequisition::class);
        $quantities = [];

        foreach ((array) $request->validated('quantities', []) as $itemId => $quantity) {
            if ($quantity !== null && $quantity !== '') {
                $quantities[(int) $itemId] = (string) $quantity;
            }
        }

        try {
            $requisition = $create->handle($this->propertyId(), array_map(intval(...), (array) $request->validated('items')), $this->userId(), $quantities, $request->filled('department_id') ? (int) $request->validated('department_id') : null);
        } catch (ProcurementRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('procurement.requisitions.show', $requisition)->with('success', __('Requisition :no raised from the reorder levels.', ['no' => $requisition->requisition_no]));
    }
}
