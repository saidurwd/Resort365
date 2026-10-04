<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Restaurant\Actions\SaveModifierGroup;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Http\Controllers\Concerns\CurrentProperty;
use Modules\Restaurant\Http\Requests\ModifierGroupRequest;
use Modules\Restaurant\Models\ModifierGroup;

/**
 * Restaurant → Modifiers: the current property's modifier groups (cooking level, add-ons…) and options.
 */
class ModifierGroupController extends Controller
{
    use CurrentProperty;

    public function index(): View
    {
        Gate::authorize('restaurant.menu.view');

        return view('restaurant::menu.modifiers', [
            'groups' => ModifierGroup::query()->where('property_id', $this->propertyId())->with('modifiers')->orderBy('name')->get(),
            'canManage' => auth()->user()?->can('restaurant.menu.manage') ?? false,
        ]);
    }

    public function store(ModifierGroupRequest $request, SaveModifierGroup $save): RedirectResponse
    {
        return $this->save($request, null, $save);
    }

    public function update(ModifierGroupRequest $request, ModifierGroup $group, SaveModifierGroup $save): RedirectResponse
    {
        return $this->save($request, $group, $save);
    }

    private function save(ModifierGroupRequest $request, ?ModifierGroup $group, SaveModifierGroup $save): RedirectResponse
    {
        try {
            $save->handle($this->propertyId(), $group, (string) $request->validated('name'), (int) $request->validated('min_select'), (int) $request->validated('max_select'),
                array_values(array_map(fn (array $modifier): array => [
                    'id' => isset($modifier['id']) ? (int) $modifier['id'] : null, 'name' => (string) $modifier['name'],
                    'price_delta' => isset($modifier['price_delta']) ? (string) $modifier['price_delta'] : '0', 'is_active' => (bool) ($modifier['is_active'] ?? true),
                ], (array) $request->validated('modifiers'))));
        } catch (RestaurantSetupInvalid $exception) {
            return to_route('restaurant.menu.modifiers.index')->withInput()->with('error', $exception->getMessage());
        }

        return to_route('restaurant.menu.modifiers.index')->with('success', __('Modifier group saved.'));
    }
}
