<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Restaurant\Actions\SaveRecipe;
use Modules\Restaurant\DTOs\NewRecipe;
use Modules\Restaurant\Enums\RecipeKind;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Http\Controllers\Concerns\CurrentProperty;
use Modules\Restaurant\Http\Requests\SubRecipeRequest;
use Modules\Restaurant\Models\Recipe;
use Modules\Restaurant\Services\Lookups\RecipeOptions;

/**
 * Restaurant → Sub-recipes: prepared components (sauces, doughs, stocks) that dishes use by the kilo or litre.
 */
class SubRecipeController extends Controller
{
    use CurrentProperty;

    public function index(): View
    {
        Gate::authorize('viewAny', Recipe::class);

        return view('restaurant::recipes.sub-index', [
            'recipes' => Recipe::query()->withCount('lines')->where('property_id', $this->propertyId())->where('kind', RecipeKind::Sub->value)->orderBy('name')->get(),
            'canManage' => auth()->user()?->can('restaurant.recipe.manage') ?? false,
        ]);
    }

    public function create(RecipeOptions $options): View
    {
        Gate::authorize('create', Recipe::class);

        return $this->form(null, $options);
    }

    public function store(SubRecipeRequest $request, SaveRecipe $save): RedirectResponse
    {
        Gate::authorize('create', Recipe::class);

        try {
            $recipe = $save->handle($this->data($request, null));
        } catch (RestaurantSetupInvalid $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('restaurant.sub-recipes.edit', $recipe)->with('success', __('Sub-recipe saved.'));
    }

    public function edit(Recipe $recipe, RecipeOptions $options): View
    {
        Gate::authorize('view', Recipe::class);
        abort_unless($recipe->kind === RecipeKind::Sub, 404);

        return $this->form($recipe->load('lines'), $options);
    }

    public function update(SubRecipeRequest $request, Recipe $recipe, SaveRecipe $save): RedirectResponse
    {
        Gate::authorize('update', Recipe::class);
        abort_unless($recipe->kind === RecipeKind::Sub, 404);

        try {
            $save->handle($this->data($request, $recipe->id));
        } catch (RestaurantSetupInvalid $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('restaurant.sub-recipes.edit', $recipe)->with('success', __('Sub-recipe saved.'));
    }

    public function destroy(Recipe $recipe, SaveRecipe $save): RedirectResponse
    {
        Gate::authorize('update', Recipe::class);
        abort_unless($recipe->kind === RecipeKind::Sub, 404);

        try {
            $save->handle(new NewRecipe($recipe->property_id, RecipeKind::Sub, [], recipeId: $recipe->id, name: $recipe->name));
        } catch (RestaurantSetupInvalid $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('restaurant.sub-recipes.index')->with('success', __('Sub-recipe deleted.'));
    }

    private function form(?Recipe $recipe, RecipeOptions $options): View
    {
        return view('restaurant::recipes.sub-form', [
            'recipe' => $recipe, 'items' => $options->items(), 'subRecipes' => $options->subRecipes($recipe?->id), 'canManage' => auth()->user()?->can('restaurant.recipe.manage') ?? false,
            'lines' => old('lines', $recipe?->lines->map(fn ($line): array => ['item_id' => $line->item_id ?? '', 'sub_recipe_id' => $line->sub_recipe_id ?? '', 'quantity' => rtrim(rtrim((string) $line->quantity, '0'), '.'), 'waste_percent' => rtrim(rtrim((string) $line->waste_percent, '0'), '.')])->all()
                ?? [['item_id' => '', 'sub_recipe_id' => '', 'quantity' => '', 'waste_percent' => '']]),
        ]);
    }

    private function data(SubRecipeRequest $request, ?int $recipeId): NewRecipe
    {
        return new NewRecipe($this->propertyId(), RecipeKind::Sub, $request->lines(), recipeId: $recipeId, name: (string) $request->validated('name'), yieldQuantity: (string) $request->validated('yield_quantity'),
            yieldUnit: $request->filled('yield_unit') ? (string) $request->validated('yield_unit') : null, notes: $request->filled('notes') ? (string) $request->validated('notes') : null);
    }
}
