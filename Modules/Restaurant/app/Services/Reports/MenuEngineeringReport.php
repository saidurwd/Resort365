<?php

namespace Modules\Restaurant\Services\Reports;

use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\MenuClass;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Services\FoodCosting;

/**
 * The menu engineering report (Step 7.2) for a property, a range of business dates, some outlets and optionally one menu
 * category: what each dish sold and earned (settled bills that someone paid for), the cost of a portion from its recipe at the
 * outlet's store (the plate cost of Restaurant → Food costing, so it follows today's ingredient prices), and the classes of the
 * pure `MenuEngineering`. Answers the table the report screen shows and the data of the scatter chart.
 *
 * A dish with no recipe at an outlet where it sold has no known cost and is listed apart.
 *
 * @phpstan-type Report array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
 */
class MenuEngineeringReport
{
    public function __construct(
        private readonly MenuEngineering $engineering,
        private readonly FoodCosting $costing,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  list<int>  $outletIds
     * @return array{table: Report, chart: array<string, mixed>, unknown: list<array{id: int, name: string, category: string, quantity: string, revenue: string}>}
     */
    public function build(int $propertyId, string $from, string $to, array $outletIds, ?int $categoryId): array
    {
        $tenantId = $this->tenant->tenantOrFail(self::class)->id;
        $groups = $this->base($tenantId, $propertyId, $from, $to, $outletIds)->groupBy('ol.menu_item_id', 'ol.menu_item_variant_id', 'b.outlet_id')
            ->get(['ol.menu_item_id', 'ol.menu_item_variant_id', 'b.outlet_id', DB::raw('SUM(bl.quantity) as quantity'), DB::raw('SUM(bl.amount - bl.discount) as revenue')]);
        $items = MenuItem::query()->with('category')->whereIn('id', $groups->pluck('menu_item_id')->unique())->when($categoryId !== null, fn ($query) => $query->where('menu_category_id', $categoryId))->get()->keyBy('id');
        $stores = Outlet::query()->whereIn('id', $outletIds)->pluck('store_id', 'id');
        $dishes = [];

        foreach ($groups as $group) {
            $item = $items->get($group->menu_item_id);

            if (! $item instanceof MenuItem) {
                continue;
            }

            $quantity = BigDecimal::of((string) $group->quantity);
            $plate = $this->costing->plate($item, $group->menu_item_variant_id !== null ? (int) $group->menu_item_variant_id : null, $stores->get($group->outlet_id));
            $dish = $dishes[$item->id] ?? [
                'id' => $item->id, 'name' => $item->translated('name'), 'category' => $item->category?->translated('name') ?? __('No category'), 'quantity' => '0', 'revenue' => '0', 'cost' => '0', 'costed' => true,
            ];
            $dish['quantity'] = (string) BigDecimal::of($dish['quantity'])->plus($quantity);
            $dish['revenue'] = (string) BigDecimal::of($dish['revenue'])->plus((string) $group->revenue);
            $dish['costed'] = $dish['costed'] && $plate !== null;
            $dish['cost'] = $plate !== null ? (string) BigDecimal::of($dish['cost'])->plus(BigDecimal::of($plate['cost'])->multipliedBy($quantity)) : $dish['cost'];
            $dishes[$item->id] = $dish;
        }

        $dishes = array_values(array_map(fn (array $dish): array => [
            'id' => $dish['id'], 'name' => $dish['name'], 'category' => $dish['category'], 'quantity' => $dish['quantity'], 'revenue' => (string) BigDecimal::of($dish['revenue'])->toScale(2),
            'cost' => $dish['costed'] ? (string) BigDecimal::of($dish['cost'])->toScale(2, RoundingMode::HalfUp) : null,
        ], $dishes));

        $result = $this->engineering->classify($dishes);
        $counts = array_fill_keys(array_map(fn (MenuClass $class): string => $class->value, MenuClass::cases()), 0);
        $rows = [];

        foreach ($result['dishes'] as $dish) {
            $counts[$dish['class']->value]++;
            $rows[] = [$dish['name'], $dish['category'], $dish['quantity'], number_format((float) $dish['price'], 2), number_format((float) $dish['cost'], 2), number_format((float) $dish['margin'], 2), $dish['popularity'].'%', number_format((float) $dish['revenue'], 2), $dish['class']->label()];
        }

        $table = [
            'title' => __('Menu engineering'),
            'columns' => [__('Dish'), __('Category'), __('Sold'), __('Price'), __('Cost'), __('Margin'), __('Popularity'), __('Revenue'), __('Class')],
            'rows' => $rows, 'totals' => null,
            'summary' => [
                ...array_map(fn (MenuClass $class): array => ['label' => $class->label().'s', 'value' => (string) $counts[$class->value]], MenuClass::cases()),
                ['label' => __('Popular from'), 'value' => $result['popularity_cut'].'%'], ['label' => __('High margin from'), 'value' => number_format((float) $result['margin_cut'], 2)],
            ],
            'note' => __('Price and margin are per portion, net of discounts and before tax. A dish is popular when its share of portions is at least 70% of an equal share, and earns a high margin when it is at least the average margin of all portions. :count dishes without a recipe (cost unknown) are not classed.', ['count' => count($result['unknown'])]),
        ];

        return ['table' => $table, 'chart' => $this->chart($result), 'unknown' => $result['unknown']];
    }

    /**
     * @param  list<int>  $outletIds
     */
    private function base(int $tenantId, int $propertyId, string $from, string $to, array $outletIds): Builder
    {
        return DB::table('pos_bill_lines as bl')->join('pos_bills as b', 'b.id', '=', 'bl.pos_bill_id')->join('pos_order_lines as ol', 'ol.id', '=', 'bl.pos_order_line_id')
            ->where('b.tenant_id', $tenantId)->where('b.property_id', $propertyId)->where('b.status', BillStatus::Settled->value)->where('b.is_complimentary', false)->whereBetween('b.business_date', [$from, $to])->whereIn('b.outlet_id', $outletIds);
    }

    /**
     * The scatter chart: popularity against margin, one dataset per class, and the two cut lines.
     *
     * @param  array{dishes: list<array{id: int, name: string, category: string, quantity: string, price: string, cost: string, margin: string, popularity: string, revenue: string, class: MenuClass}>, unknown: list<array<string, mixed>>, popularity_cut: string, margin_cut: string}  $result
     * @return array<string, mixed>
     */
    private function chart(array $result): array
    {
        $colours = ['star' => '#198754', 'plowhorse' => '#ffc107', 'puzzle' => '#0dcaf0', 'dog' => '#dc3545'];
        $datasets = [];
        $maxX = (float) $result['popularity_cut'] * 1.5;
        $maxY = (float) $result['margin_cut'] * 1.5;
        $minY = 0.0;

        foreach (MenuClass::cases() as $class) {
            $points = [];

            foreach ($result['dishes'] as $dish) {
                if ($dish['class'] === $class) {
                    $points[] = ['x' => (float) $dish['popularity'], 'y' => (float) $dish['margin'], 'name' => $dish['name']];
                    $maxX = max($maxX, (float) $dish['popularity']);
                    $maxY = max($maxY, (float) $dish['margin']);
                    $minY = min($minY, (float) $dish['margin']);
                }
            }

            $datasets[] = ['type' => 'scatter', 'label' => $class->label(), 'data' => $points, 'backgroundColor' => $colours[$class->value], 'pointRadius' => 6];
        }

        $datasets[] = ['type' => 'line', 'label' => __('Popularity cut'), 'data' => [['x' => (float) $result['popularity_cut'], 'y' => $minY], ['x' => (float) $result['popularity_cut'], 'y' => $maxY * 1.1]], 'borderColor' => '#6c757d', 'borderDash' => [6, 4], 'pointRadius' => 0, 'fill' => false];
        $datasets[] = ['type' => 'line', 'label' => __('Margin cut'), 'data' => [['x' => 0, 'y' => (float) $result['margin_cut']], ['x' => $maxX * 1.1, 'y' => (float) $result['margin_cut']]], 'borderColor' => '#6c757d', 'borderDash' => [2, 3], 'pointRadius' => 0, 'fill' => false];

        return [
            'type' => 'scatter', 'data' => ['datasets' => $datasets], 'pointLabels' => true,
            'options' => ['scales' => ['x' => ['type' => 'linear', 'min' => 0, 'title' => ['display' => true, 'text' => __('Popularity (% of portions sold)')]], 'y' => ['title' => ['display' => true, 'text' => __('Margin per portion')]]]],
        ];
    }
}
