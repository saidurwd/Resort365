<?php

namespace Modules\Restaurant\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Blade;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\Outlet;
use Yajra\DataTables\DataTables;

/**
 * Server-side DataTable of a property's menu items: code, name, category, course, kind, variants and
 * in which outlets they are sold.
 */
class MenuItemsTable
{
    public function __construct(
        private readonly DataTables $dataTables,
    ) {}

    /**
     * @return list<array{data: string, title: string, orderable?: bool, searchable?: bool, className?: string}>
     */
    public static function columns(): array
    {
        return [
            ['data' => 'code', 'title' => __('Code')],
            ['data' => 'name', 'title' => __('Item'), 'orderable' => false],
            ['data' => 'category', 'title' => __('Category'), 'orderable' => false, 'searchable' => false],
            ['data' => 'course', 'title' => __('Course'), 'searchable' => false],
            ['data' => 'kind', 'title' => __('Kind'), 'searchable' => false],
            ['data' => 'variants', 'title' => __('Variants'), 'orderable' => false, 'searchable' => false],
            ['data' => 'outlets', 'title' => __('Sold at'), 'orderable' => false, 'searchable' => false],
            ['data' => 'is_active', 'title' => __('Active'), 'searchable' => false],
        ];
    }

    public function toJson(int $propertyId, ?int $categoryId): JsonResponse
    {
        $outlets = Outlet::query()->where('property_id', $propertyId)->pluck('code', 'id');
        $query = MenuItem::query()->select('menu_items.*')->where('property_id', $propertyId)->with(['category', 'variants', 'outletPrices'])
            ->when($categoryId !== null, fn ($query) => $query->where('menu_category_id', $categoryId));

        return $this->dataTables->eloquent($query)
            ->editColumn('code', fn (MenuItem $item): string => '<a href="'.e(route('restaurant.menu.items.edit', $item)).'" class="fw-semibold">'.e($item->code).'</a>')
            ->addColumn('name', fn (MenuItem $item): string => e($item->translated('name'))
                .($item->dietary_tags ? ' <span class="small text-body-secondary">'.e(implode(' · ', $item->dietary_tags)).'</span>' : ''))
            ->filterColumn('name', fn ($query, string $keyword) => $query->where('name', 'like', '%'.$keyword.'%'))
            ->addColumn('category', fn (MenuItem $item): string => e($item->category?->translated('name') ?? ''))
            ->editColumn('course', fn (MenuItem $item): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $item->course]))
            ->editColumn('kind', fn (MenuItem $item): string => e($item->kind->label()))
            ->addColumn('variants', fn (MenuItem $item): string => e($item->variants->pluck('name')->implode(' / ')))
            ->addColumn('outlets', fn (MenuItem $item): string => e($item->outletPrices->pluck('outlet_id')->unique()->map(fn ($id): string => (string) ($outlets[$id] ?? ''))->sort()->implode(', ')))
            ->editColumn('is_active', fn (MenuItem $item): string => $item->is_active ? '<span class="badge text-bg-success">'.e(__('Yes')).'</span>' : '<span class="badge text-bg-secondary">'.e(__('No')).'</span>')
            ->rawColumns(['code', 'name', 'course', 'is_active'])
            ->toJson();
    }
}
