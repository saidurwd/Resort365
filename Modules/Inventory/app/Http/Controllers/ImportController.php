<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Inventory\Actions\ImportItems;
use Modules\Inventory\Exceptions\InventoryRuleViolated;
use Modules\Inventory\Http\Requests\ImportItemsRequest;
use Modules\Inventory\Models\Item;

/**
 * Inventory → Import items: a CSV of items, all or nothing.
 */
class ImportController extends Controller
{
    public function create(): View
    {
        Gate::authorize('import', Item::class);

        return view('inventory::import.create');
    }

    public function store(ImportItemsRequest $request, ImportItems $import): RedirectResponse
    {
        Gate::authorize('import', Item::class);

        try {
            $result = $import->handle((string) file_get_contents((string) $request->file('file')?->getRealPath()));
        } catch (InventoryRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('inventory.items.index')->with('success', __(':created items added, :updated updated.', ['created' => $result['created'], 'updated' => $result['updated']]));
    }
}
