<?php

namespace Modules\Housekeeping\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Housekeeping\Http\Controllers\Concerns\HousekeepingScreen;
use Modules\Housekeeping\Http\Requests\IssueAmenitiesRequest;
use Modules\Inventory\Contracts\StockIssuing;
use Modules\Inventory\Contracts\StoreDirectory;
use Modules\Inventory\DTOs\NewStockIssue;
use Modules\Inventory\Enums\StoreKind;
use Modules\Inventory\Exceptions\StockRuleViolated;

/**
 * Housekeeping → My tasks → Issue amenities: an attendant takes amenities out of the property's housekeeping store;
 * Inventory issues them to the Housekeeping department (Step 5.4).
 */
class AmenityController extends Controller
{
    use HousekeepingScreen;

    public function issue(IssueAmenitiesRequest $request, StoreDirectory $stores, StockIssuing $issuing): RedirectResponse
    {
        Gate::authorize('housekeeping.task.perform');
        $property = $this->property();
        $lines = $request->lines();
        $store = collect($stores->forProperty($property->id))->first(fn ($store): bool => $store->kind === StoreKind::Housekeeping);

        if ($store === null) {
            return back()->with('error', __('This property has no housekeeping store.'));
        }

        if ($lines === []) {
            return back()->with('error', __('Enter a quantity for at least one item.'));
        }

        $room = $request->filled('room_id') ? ($this->roomOptions($property->id)[(int) $request->validated('room_id')] ?? null) : null;

        try {
            $issue = $issuing->issue(new NewStockIssue(
                storeId: $store->id, departmentId: $store->departmentId, lines: $lines, reason: __('Room amenities'),
                notes: $room !== null ? __('Room :room', ['room' => $room]) : null, sourceType: 'housekeeping_task', userId: $this->userId(),
            ));
        } catch (StockRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('Amenities issued (:no).', ['no' => $issue->issueNo]));
    }
}
