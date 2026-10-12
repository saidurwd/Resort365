<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Restaurant\Actions\SaveItemRecipes;
use Modules\Restaurant\Enums\RecipeKind;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Http\Controllers\Concerns\CurrentProperty;
use Modules\Restaurant\Http\Requests\RecipeRequest;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\Modifier;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\Recipe;
use Modules\Restaurant\Services\FoodCosting;
use Modules\Restaurant\Services\Lookups\RecipeOptions;

/**
 * Restaurant → Menu → an item's recipe: what goes into one portion, per variant and per modifier, with the plate cost at
 * each outlet.
 */
class RecipeController extends Controller
{
    use CurrentProperty;

    public function edit(MenuItem $item, RecipeOptions $options, FoodCosting $costing, ItemCatalog $catalog): View
    {
        Gate::authorize('view', Recipe::class);
        $item->load(['variants', 'modifierGroups.modifiers']);
        $sections = [['key' => 'dish', 'title' => __('Base recipe'), 'hint' => __('One portion of :item.', ['item' => $item->translated('name')])]];

        foreach ($item->variants as $variant) {
            $sections[] = ['key' => 'variant:'.$variant->id, 'title' => __('Variant: :name', ['name' => $variant->name]), 'hint' => __('Replaces the base recipe when this variant is sold.')];
        }

        foreach ($item->modifierGroups as $group) {
            foreach ($group->modifiers as $modifier) {
                /** @var Modifier $modifier */
                $sections[] = ['key' => 'modifier:'.$modifier->id, 'title' => __('Modifier: :group · :name', ['group' => $group->name, 'name' => $modifier->name]), 'hint' => __('Added to the dish when chosen.')];
            }
        }

        $recipes = Recipe::query()->with('lines')->where(fn ($query) => $query->where('menu_item_id', $item->id)->orWhereIn('modifier_id', $item->modifierGroups->flatMap->modifiers->pluck('id')))->get();
        $linesOf = function (string $key) use ($recipes): array {
            [$kind, $id] = array_pad(explode(':', $key, 2), 2, null);
            $recipe = match ($kind) {
                'variant' => $recipes->first(fn (Recipe $candidate): bool => $candidate->kind === RecipeKind::Variant && $candidate->menu_item_variant_id === (int) $id),
                'modifier' => $recipes->first(fn (Recipe $candidate): bool => $candidate->kind === RecipeKind::Modifier && $candidate->modifier_id === (int) $id),
                default => $recipes->first(fn (Recipe $candidate): bool => $candidate->kind === RecipeKind::Dish),
            };

            return $recipe?->lines->map(fn ($line): array => ['item_id' => $line->item_id ?? '', 'sub_recipe_id' => $line->sub_recipe_id ?? '', 'quantity' => rtrim(rtrim((string) $line->quantity, '0'), '.'), 'waste_percent' => rtrim(rtrim((string) $line->waste_percent, '0'), '.')])->all() ?? [];
        };
        $state = [];

        foreach ($sections as $section) {
            $state[$section['key']] = old('sections.'.$section['key'], $linesOf($section['key']));
        }

        $plates = [];

        foreach (Outlet::query()->where('property_id', $item->property_id)->whereNotNull('store_id')->orderBy('name')->get() as $outlet) {
            $plates[$outlet->name] = $costing->plate($item, null, $outlet->store_id)['cost'] ?? null;
        }

        return view('restaurant::recipes.edit', [
            'item' => $item, 'sections' => $sections, 'state' => $state, 'items' => $options->items(), 'subRecipes' => $options->subRecipes(), 'plates' => $plates, 'units' => collect($catalog->search('', 500))->mapWithKeys(fn ($found): array => [$found->id => $found->baseUnit])->all(),
            'canManage' => auth()->user()?->can('restaurant.recipe.manage') ?? false,
        ]);
    }

    public function update(RecipeRequest $request, MenuItem $item, SaveItemRecipes $save): RedirectResponse
    {
        Gate::authorize('update', Recipe::class);

        try {
            $save->handle($item, $request->sections());
        } catch (RestaurantSetupInvalid $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('restaurant.recipes.edit', $item)->with('success', __('Recipe saved.'));
    }
}
