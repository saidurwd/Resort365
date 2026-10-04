<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Restaurant\Actions\SaveMenuItem;
use Modules\Restaurant\Enums\Allergen;
use Modules\Restaurant\Enums\Course;
use Modules\Restaurant\Enums\DietaryTag;
use Modules\Restaurant\Enums\MenuItemKind;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Http\Controllers\Concerns\CurrentProperty;
use Modules\Restaurant\Http\Requests\MenuItemRequest;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\ModifierGroup;
use Modules\Restaurant\Services\MenuItemsTable;
use Modules\Restaurant\Services\MenuLanguages;

/**
 * Restaurant → Menu: the current property's menu items (list, add, change), with their variants,
 * modifier groups, combo components and photo.
 */
class MenuItemController extends Controller
{
    use CurrentProperty;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MenuItem::class);
        $propertyId = $this->propertyId();

        return view('restaurant::menu.items.index', [
            'columns' => MenuItemsTable::columns(),
            'categories' => $this->categoryOptions($propertyId),
            'category' => $request->integer('category') ?: null,
        ]);
    }

    public function data(Request $request, MenuItemsTable $table): JsonResponse
    {
        Gate::authorize('viewAny', MenuItem::class);

        return $table->toJson($this->propertyId(), $request->integer('category') ?: null);
    }

    public function create(MenuLanguages $languages): View
    {
        Gate::authorize('create', MenuItem::class);

        return $this->form(null, $languages);
    }

    public function store(MenuItemRequest $request, SaveMenuItem $save): RedirectResponse
    {
        try {
            $item = $save->handle($this->propertyId(), null, $this->payload($request));
        } catch (RestaurantSetupInvalid $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('restaurant.menu.items.edit', $item)->with('success', __('Item saved. Price it on each outlet\'s price list.'));
    }

    public function edit(MenuItem $item, MenuLanguages $languages): View
    {
        Gate::authorize('view', $item);

        return $this->form($item->load(['variants', 'modifierGroups', 'components', 'outletPrices']), $languages);
    }

    public function update(MenuItemRequest $request, MenuItem $item, SaveMenuItem $save): RedirectResponse
    {
        try {
            $save->handle($item->property_id, $item, $this->payload($request));
        } catch (RestaurantSetupInvalid $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('restaurant.menu.items.edit', $item)->with('success', __('Item saved.'));
    }

    private function form(?MenuItem $item, MenuLanguages $languages): View
    {
        $propertyId = $item->property_id ?? $this->propertyId();

        return view('restaurant::menu.items.form', [
            'item' => $item,
            'languages' => $languages->all(),
            'categories' => $this->categoryOptions($propertyId),
            'courses' => Course::options(),
            'kinds' => MenuItemKind::options(),
            'dietaryTags' => DietaryTag::options(),
            'allergens' => Allergen::options(),
            'taxCategories' => $this->taxCategoryOptions(),
            'groups' => ModifierGroup::query()->where('property_id', $propertyId)->orderBy('name')->pluck('name', 'id')->all(),
            'comboItems' => MenuItem::query()->where('property_id', $propertyId)->where('kind', '!=', MenuItemKind::Combo->value)->with('variants')->orderBy('code')->get()
                ->flatMap(fn (MenuItem $option): array => $option->variants->isEmpty()
                    ? [$option->id.':' => $option->code.' · '.$option->translated('name')]
                    : $option->variants->mapWithKeys(fn ($variant): array => [$option->id.':'.$variant->id => $option->code.' · '.$option->translated('name').' ('.$variant->name.')'])->all())
                ->all(),
            'canManage' => auth()->user()?->can('create', MenuItem::class) ?? false,
        ]);
    }

    /**
     * @return array{menu_category_id: int, code: string, name: array<string, string|null>, description: array<string, string|null>, course: string,
     *     tax_category_id: int|null, kind: string, dietary_tags: list<string>, allergens: list<string>, sort_order: int, is_active: bool,
     *     variants: list<string>, modifier_group_ids: list<int>, components: list<array{item_id: int, variant_id: int|null, quantity: int}>}
     */
    private function payload(MenuItemRequest $request): array
    {
        return [
            'menu_category_id' => (int) $request->validated('menu_category_id'),
            'code' => (string) $request->validated('code'),
            'name' => (array) $request->validated('name'),
            'description' => (array) ($request->validated('description') ?? []),
            'course' => (string) $request->validated('course'),
            'tax_category_id' => $request->filled('tax_category_id') ? (int) $request->validated('tax_category_id') : null,
            'kind' => (string) $request->validated('kind'),
            'dietary_tags' => array_values((array) ($request->validated('dietary_tags') ?? [])),
            'allergens' => array_values((array) ($request->validated('allergens') ?? [])),
            'sort_order' => (int) ($request->validated('sort_order') ?? 0),
            'is_active' => $request->boolean('is_active', true),
            'variants' => array_values(array_filter(array_map(fn ($name): string => trim((string) $name), (array) ($request->validated('variants') ?? [])))),
            'modifier_group_ids' => array_values(array_map(intval(...), (array) ($request->validated('modifier_group_ids') ?? []))),
            'components' => array_values(array_map(fn (array $component): array => ['item_id' => (int) $component['item_id'],
                'variant_id' => ($component['variant_id'] ?? null) !== null ? (int) $component['variant_id'] : null, 'quantity' => (int) ($component['quantity'] ?? 1)],
                (array) ($request->validated('components') ?? []))),
        ];
    }
}
