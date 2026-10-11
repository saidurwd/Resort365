<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Inventory\Actions\SaveCategory;
use Modules\Inventory\Enums\StockClass;
use Modules\Inventory\Exceptions\InventoryRuleViolated;
use Modules\Inventory\Http\Requests\CategoryRequest;
use Modules\Inventory\Models\ItemCategory;

/**
 * Inventory → Categories: the tree of item categories.
 */
class CategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', ItemCategory::class);
        $categories = ItemCategory::query()->with('parent')->withCount('items')->orderBy('sort_order')->orderBy('name')->get();

        return view('inventory::categories.index', [
            'rows' => $categories->sortBy(fn (ItemCategory $category): string => mb_strtolower($category->path()))->values(),
            'options' => $categories->mapWithKeys(fn (ItemCategory $category): array => [$category->id => $category->path()])->all(),
            'classes' => collect(StockClass::cases())->mapWithKeys(fn (StockClass $class): array => [$class->value => $class->label()])->all(),
            'canManage' => auth()->user()?->can('inventory.catalog.manage') ?? false,
        ]);
    }

    public function store(CategoryRequest $request, SaveCategory $save): RedirectResponse
    {
        Gate::authorize('create', ItemCategory::class);

        return $this->save($request, null, $save);
    }

    public function update(CategoryRequest $request, ItemCategory $category, SaveCategory $save): RedirectResponse
    {
        Gate::authorize('update', $category);

        return $this->save($request, $category, $save);
    }

    private function save(CategoryRequest $request, ?ItemCategory $category, SaveCategory $save): RedirectResponse
    {
        try {
            $save->handle($category, [
                'name' => (string) $request->validated('name'), 'parent_id' => $request->filled('parent_id') ? (int) $request->validated('parent_id') : null,
                'account_class' => $request->filled('account_class') ? (string) $request->validated('account_class') : null, 'sort_order' => (int) ($request->validated('sort_order') ?? 0), 'is_active' => $request->boolean('is_active', true),
            ]);
        } catch (InventoryRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('inventory.categories.index')->with('success', __('Category saved.'));
    }
}
