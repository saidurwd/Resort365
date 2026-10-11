<?php

namespace Modules\Inventory\Services;

use Illuminate\Http\JsonResponse;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Yajra\DataTables\DataTables;

/**
 * The items list (server-side DataTable) with filters for category (including its subcategories), active and stockable.
 */
class ItemsTable
{
    public function __construct(private readonly DataTables $dataTables) {}

    /**
     * @return list<array{data: string, title: string, orderable?: bool, searchable?: bool, className?: string}>
     */
    public static function columns(): array
    {
        return [
            ['data' => 'sku', 'title' => __('SKU')],
            ['data' => 'name', 'title' => __('Item')],
            ['data' => 'category', 'title' => __('Category'), 'orderable' => false, 'searchable' => false],
            ['data' => 'unit', 'title' => __('Unit'), 'orderable' => false, 'searchable' => false],
            ['data' => 'reorder_level', 'title' => __('Reorder at'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'standard_cost', 'title' => __('Standard cost'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'flags', 'title' => '', 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
        ];
    }

    public function toJson(?int $categoryId, ?string $active, ?string $stockable, bool $canManage): JsonResponse
    {
        $categoryIds = $categoryId !== null ? $this->withDescendants($categoryId) : null;
        $query = Item::query()->with(['category', 'baseUnit'])
            ->when($categoryIds !== null, fn ($query) => $query->whereIn('item_category_id', $categoryIds))
            ->when($active === '1' || $active === '0', fn ($query) => $query->where('is_active', $active === '1'))
            ->when($stockable === '1' || $stockable === '0', fn ($query) => $query->where('is_stockable', $stockable === '1'));

        return $this->dataTables->eloquent($query)
            ->addColumn('category', fn (Item $item): string => e($item->category->path()))
            ->addColumn('unit', fn (Item $item): string => e($item->baseUnit->code))
            ->editColumn('name', fn (Item $item): string => e($item->name).($item->barcode ? ' <span class="small text-body-secondary">'.e($item->barcode).'</span>' : ''))
            ->editColumn('reorder_level', fn (Item $item): string => (float) $item->reorder_level > 0 ? rtrim(rtrim(number_format((float) $item->reorder_level, 4), '0'), '.') : '')
            ->editColumn('standard_cost', fn (Item $item): string => $item->standard_cost !== null ? number_format((float) $item->standard_cost, 2) : '')
            ->addColumn('flags', fn (Item $item): string => implode(' ', array_filter([
                $item->is_stockable ? null : '<span class="badge text-bg-light border">'.e(__('service')).'</span>', $item->track_batches ? '<span class="badge text-bg-info">'.e(__('batches')).'</span>' : null,
                $item->track_expiry ? '<span class="badge text-bg-warning">'.e(__('expiry')).'</span>' : null, $item->is_active ? null : '<span class="badge text-bg-secondary">'.e(__('inactive')).'</span>',
            ])))
            ->addColumn('actions', fn (Item $item): string => $canManage ? '<a class="btn btn-sm btn-outline-secondary" href="'.e(route('inventory.items.edit', $item)).'">'.e(__('Change')).'</a>' : '')
            ->rawColumns(['name', 'flags', 'actions'])
            ->toJson();
    }

    /**
     * @return list<int>
     */
    private function withDescendants(int $categoryId): array
    {
        $ids = [$categoryId];
        $pending = [$categoryId];

        while ($pending !== []) {
            $children = ItemCategory::query()->whereIn('parent_id', $pending)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $pending = array_values(array_diff($children, $ids));
            $ids = [...$ids, ...$pending];
        }

        return $ids;
    }
}
