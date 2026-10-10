<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Restaurant\Actions\SaveMenuCategory;
use Modules\Restaurant\Enums\RevenueClass;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Http\Controllers\Concerns\CurrentProperty;
use Modules\Restaurant\Http\Requests\MenuCategoryRequest;
use Modules\Restaurant\Models\MenuCategory;
use Modules\Restaurant\Services\MenuLanguages;

/**
 * Restaurant → Menu categories: the current property's category tree, added to and edited in place.
 */
class MenuCategoryController extends Controller
{
    use CurrentProperty;

    public function index(MenuLanguages $languages): View
    {
        Gate::authorize('restaurant.menu.view');
        $propertyId = $this->propertyId();

        return view('restaurant::menu.categories', [
            'categories' => MenuCategory::query()->where('property_id', $propertyId)->withCount('items')->orderBy('sort_order')->get(),
            'options' => $this->categoryOptions($propertyId),
            'languages' => $languages->all(),
            'colours' => ['primary' => __('Teal'), 'success' => __('Green'), 'info' => __('Blue'), 'warning' => __('Amber'), 'danger' => __('Red'), 'secondary' => __('Grey')],
            'revenueClasses' => collect(RevenueClass::cases())->mapWithKeys(fn (RevenueClass $class): array => [$class->value => $class->label()])->all(),
            'canManage' => auth()->user()?->can('restaurant.menu.manage') ?? false,
        ]);
    }

    public function store(MenuCategoryRequest $request, SaveMenuCategory $save): RedirectResponse
    {
        return $this->save($request, null, $save);
    }

    public function update(MenuCategoryRequest $request, MenuCategory $category, SaveMenuCategory $save): RedirectResponse
    {
        return $this->save($request, $category, $save);
    }

    private function save(MenuCategoryRequest $request, ?MenuCategory $category, SaveMenuCategory $save): RedirectResponse
    {
        try {
            $save->handle($this->propertyId(), $category, [
                'name' => (array) $request->validated('name'), 'parent_id' => $request->filled('parent_id') ? (int) $request->validated('parent_id') : null,
                'colour' => (string) $request->validated('colour'), 'revenue_class' => $request->filled('revenue_class') ? (string) $request->validated('revenue_class') : null, 'sort_order' => (int) ($request->validated('sort_order') ?? 0), 'is_active' => $request->boolean('is_active', true),
            ]);
        } catch (RestaurantSetupInvalid $exception) {
            return to_route('restaurant.menu.categories.index')->with('error', $exception->getMessage());
        }

        return to_route('restaurant.menu.categories.index')->with('success', __('Category saved.'));
    }
}
