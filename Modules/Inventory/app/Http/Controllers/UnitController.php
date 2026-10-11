<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Inventory\Actions\SaveUnit;
use Modules\Inventory\Enums\UnitKind;
use Modules\Inventory\Exceptions\InventoryRuleViolated;
use Modules\Inventory\Http\Requests\UnitRequest;
use Modules\Inventory\Models\Unit;

/**
 * Inventory → Units: units of measure and how units of one kind convert.
 */
class UnitController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Unit::class);
        $units = Unit::query()->with('baseUnit')->withCount('items')->orderBy('kind')->orderBy('factor')->orderBy('code')->get();

        return view('inventory::units.index', [
            'units' => $units, 'kinds' => collect(UnitKind::cases())->mapWithKeys(fn (UnitKind $kind): array => [$kind->value => $kind->label()])->all(),
            'bases' => $units->whereNull('base_unit_id')->mapWithKeys(fn (Unit $unit): array => [$unit->id => $unit->code.' · '.$unit->name.' ('.mb_strtolower($unit->kind->label()).')'])->all(),
            'canManage' => auth()->user()?->can('inventory.catalog.manage') ?? false,
        ]);
    }

    public function store(UnitRequest $request, SaveUnit $save): RedirectResponse
    {
        Gate::authorize('create', Unit::class);

        return $this->save($request, null, $save);
    }

    public function update(UnitRequest $request, Unit $unit, SaveUnit $save): RedirectResponse
    {
        Gate::authorize('update', $unit);

        return $this->save($request, $unit, $save);
    }

    private function save(UnitRequest $request, ?Unit $unit, SaveUnit $save): RedirectResponse
    {
        try {
            $save->handle($unit, [
                'code' => (string) $request->validated('code'), 'name' => (string) $request->validated('name'), 'kind' => (string) $request->validated('kind'),
                'base_unit_id' => $request->filled('base_unit_id') ? (int) $request->validated('base_unit_id') : null, 'factor' => $request->filled('factor') ? (string) $request->validated('factor') : null,
                'is_active' => $request->boolean('is_active', true),
            ]);
        } catch (InventoryRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('inventory.units.index')->with('success', __('Unit saved.'));
    }
}
