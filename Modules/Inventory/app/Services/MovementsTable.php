<?php

namespace Modules\Inventory\Services;

use Illuminate\Http\JsonResponse;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Models\StockMovement;
use Yajra\DataTables\DataTables;

/**
 * The stock movement history (server-side DataTable), newest first, filtered by store, item, type and dates.
 */
class MovementsTable
{
    public function __construct(private readonly DataTables $dataTables) {}

    /**
     * @return list<array{data: string, title: string, orderable?: bool, searchable?: bool, className?: string}>
     */
    public static function columns(): array
    {
        return [
            ['data' => 'id', 'title' => '#', 'searchable' => false],
            ['data' => 'movement_date', 'title' => __('Date'), 'searchable' => false],
            ['data' => 'store', 'title' => __('Store'), 'orderable' => false, 'searchable' => false],
            ['data' => 'item', 'title' => __('Item'), 'orderable' => false, 'searchable' => false],
            ['data' => 'movement_type', 'title' => __('Type'), 'orderable' => false, 'searchable' => false],
            ['data' => 'quantity', 'title' => __('Quantity'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'unit_cost', 'title' => __('Unit cost'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'total_cost', 'title' => __('Value'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'quantity_after', 'title' => __('Balance after'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'notes', 'title' => __('Note')],
        ];
    }

    /**
     * @param  array{store?: int|null, item?: int|null, type?: string|null, from?: string|null, to?: string|null}  $filter
     */
    public function toJson(array $filter): JsonResponse
    {
        $query = StockMovement::query()->with(['store', 'item.baseUnit'])
            ->when(($filter['store'] ?? null) !== null, fn ($query) => $query->where('store_id', $filter['store']))
            ->when(($filter['item'] ?? null) !== null, fn ($query) => $query->where('item_id', $filter['item']))
            ->when(MovementType::tryFrom((string) ($filter['type'] ?? '')) instanceof MovementType, fn ($query) => $query->where('movement_type', $filter['type']))
            ->when(($filter['from'] ?? null) !== null, fn ($query) => $query->where('movement_date', '>=', $filter['from']))
            ->when(($filter['to'] ?? null) !== null, fn ($query) => $query->where('movement_date', '<=', $filter['to']));
        $plain = fn (string $number): string => str_contains($number, '.') ? rtrim(rtrim($number, '0'), '.') : $number;

        return $this->dataTables->eloquent($query)
            ->editColumn('movement_date', fn (StockMovement $row): string => $row->movement_date->format('d M Y'))
            ->addColumn('store', fn (StockMovement $row): string => e($row->store->name))
            ->addColumn('item', fn (StockMovement $row): string => e($row->item->sku.' · '.$row->item->name))
            ->editColumn('movement_type', fn (StockMovement $row): string => '<span class="badge text-bg-'.$row->movement_type->color().'">'.e($row->movement_type->label()).'</span>')
            ->editColumn('quantity', fn (StockMovement $row): string => $plain((string) $row->quantity).' '.e($row->item->baseUnit->code))
            ->editColumn('unit_cost', fn (StockMovement $row): string => number_format((float) $row->unit_cost, 4))
            ->editColumn('total_cost', fn (StockMovement $row): string => number_format((float) $row->total_cost, 2))
            ->editColumn('quantity_after', fn (StockMovement $row): string => $plain((string) $row->quantity_after))
            ->editColumn('notes', fn (StockMovement $row): string => e((string) $row->notes))
            ->rawColumns(['movement_type'])
            ->orderColumn('id', 'id $1')
            ->toJson();
    }
}
