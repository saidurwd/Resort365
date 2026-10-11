<?php

namespace Modules\Inventory\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Inventory\Actions\IssueStock;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Http\Requests\DirectIssueRequest;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\Store;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * Inventory → Direct issue: the store keeper puts items out to a department without a requisition.
 */
class DirectIssueController extends Controller
{
    public function create(PropertyContext $property, DepartmentDirectory $departments): View
    {
        Gate::authorize('issue', StockBalance::class);

        return view('inventory::issues.create', [
            'stores' => Store::query()->where('is_active', true)->when($property->currentId() !== null, fn ($query) => $query->where('property_id', $property->currentId()))->orderBy('name')->pluck('name', 'id')->all(),
            'departments' => collect($departments->all())->mapWithKeys(fn ($department): array => [$department->id => $department->name])->all(),
            'items' => Item::query()->where('is_stockable', true)->with('baseUnit')->orderBy('name')->get()->mapWithKeys(fn (Item $item): array => [$item->id => $item->sku.' · '.$item->name.' ('.$item->baseUnit->code.')'])->all(),
            'lines' => old('lines', [['item_id' => '', 'quantity' => '']]),
        ]);
    }

    public function store(DirectIssueRequest $request, IssueStock $issue): RedirectResponse
    {
        Gate::authorize('issue', StockBalance::class);

        try {
            $document = $issue->handle((int) $request->validated('store_id'), (int) $request->validated('department_id'), $request->lines(), (string) $request->validated('reason'),
                $request->filled('notes') ? (string) $request->validated('notes') : null, (int) $request->user()?->getAuthIdentifier());
        } catch (StockRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('inventory.stock.index', ['store' => $document->storeId])->with('success', __('Issue :no recorded: :value charged to the department.', ['no' => $document->issueNo, 'value' => number_format((float) $document->totalCost, 2)]));
    }
}
