<?php

namespace Modules\Inventory\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Inventory\Actions\SaveStore;
use Modules\Inventory\Enums\StoreKind;
use Modules\Inventory\Exceptions\InventoryRuleViolated;
use Modules\Inventory\Http\Requests\StoreRequest;
use Modules\Inventory\Models\Store;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * Inventory → Stores: the stores of the current property, each tied to a department.
 */
class StoreController extends Controller
{
    public function index(PropertyContext $property, DepartmentDirectory $departments): View
    {
        Gate::authorize('viewAny', Store::class);
        $names = [];

        foreach ($departments->all(false) as $department) {
            $names[$department->id] = $department->name;
        }

        return view('inventory::stores.index', [
            'stores' => Store::query()->when($property->currentId() !== null, fn ($query) => $query->where('property_id', $property->currentId()))->orderBy('name')->get(),
            'kinds' => collect(StoreKind::cases())->mapWithKeys(fn (StoreKind $kind): array => [$kind->value => $kind->label()])->all(),
            'departments' => $names, 'canManage' => auth()->user()?->can('inventory.store.manage') ?? false, 'propertyName' => $property->currentName(),
        ]);
    }

    public function store(StoreRequest $request, SaveStore $save, PropertyContext $property): RedirectResponse
    {
        Gate::authorize('create', Store::class);

        return $this->save($request, null, $save, $property);
    }

    public function update(StoreRequest $request, Store $store, SaveStore $save, PropertyContext $property): RedirectResponse
    {
        Gate::authorize('update', $store);

        return $this->save($request, $store, $save, $property);
    }

    private function save(StoreRequest $request, ?Store $store, SaveStore $save, PropertyContext $property): RedirectResponse
    {
        $propertyId = $store instanceof Store ? $store->property_id : $property->currentId();

        if ($propertyId === null) {
            return back()->withInput()->with('error', __('Choose a property first.'));
        }

        try {
            $save->handle($propertyId, $store, [
                'code' => (string) $request->validated('code'), 'name' => (string) $request->validated('name'), 'kind' => (string) $request->validated('kind'),
                'department_id' => $request->filled('department_id') ? (int) $request->validated('department_id') : null, 'is_active' => $request->boolean('is_active', true),
            ]);
        } catch (InventoryRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('inventory.stores.index')->with('success', __('Store saved.'));
    }
}
