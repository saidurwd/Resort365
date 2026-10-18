<?php

namespace Modules\Restaurant\Services\Reports;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Restaurant\Contracts\RestaurantFacts;
use Modules\Restaurant\DTOs\BillFact;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\SaleConsumption;

/**
 * Food and beverage cost percent (Step 5.8): what the ingredients of the sales cost against what the sales brought in, per
 * outlet and class. Complimentary and staff-meal food is shown apart, because nobody paid for it.
 */
class CostReports
{
    public function __construct(
        private readonly RestaurantFacts $facts,
        private readonly ItemCatalog $items,
    ) {}

    /**
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function foodCost(int $propertyId, string $from, string $to, ?int $outletId): array
    {
        $outlets = Outlet::query()->where('property_id', $propertyId)->when($outletId !== null, fn ($query) => $query->whereKey($outletId))->orderBy('name')->get()->keyBy('id');
        $sales = [];

        foreach (PosBill::query()->whereIn('outlet_id', $outlets->keys())->whereBetween('business_date', [$from, $to])->where('status', BillStatus::Settled->value)->where('is_complimentary', false)->get() as $bill) {
            $fact = $this->facts->bill($bill->id);

            foreach ($fact instanceof BillFact ? $fact->revenue : [] as $class => $net) {
                $sales[$bill->outlet_id][$class] = ($sales[$bill->outlet_id][$class] ?? BigDecimal::zero())->plus($net);
            }
        }

        $consumption = SaleConsumption::query()->whereIn('outlet_id', $outlets->keys())->whereBetween('business_date', [$from, $to])->whereNull('reversed_at')->get();
        $classes = $this->items->many($consumption->pluck('item_id')->unique()->all());
        $costs = [];
        $free = [];

        foreach ($consumption as $row) {
            $class = ($classes[$row->item_id]->accountClass ?? 'food') === 'beverage' ? 'beverage' : 'food';

            if ($row->is_complimentary) {
                $free[$row->outlet_id][$class] = ($free[$row->outlet_id][$class] ?? BigDecimal::zero())->plus($row->total_cost);
            } else {
                $costs[$row->outlet_id][$class] = ($costs[$row->outlet_id][$class] ?? BigDecimal::zero())->plus($row->total_cost);
            }
        }

        $rows = [];
        $total = ['sales' => BigDecimal::zero(), 'cost' => BigDecimal::zero(), 'free' => BigDecimal::zero()];

        foreach ($outlets as $outlet) {
            foreach (['food' => __('Food'), 'beverage' => __('Beverage')] as $class => $label) {
                $net = $sales[$outlet->id][$class] ?? BigDecimal::zero();
                $cost = $costs[$outlet->id][$class] ?? BigDecimal::zero();
                $comped = $free[$outlet->id][$class] ?? BigDecimal::zero();

                if ($net->isZero() && $cost->isZero() && $comped->isZero()) {
                    continue;
                }

                $rows[] = [$outlet->name, $label, $this->money((string) $net), $this->money((string) $cost), $net->isPositive() ? $cost->multipliedBy('100')->dividedBy($net, 2, RoundingMode::HalfUp).'%' : '—', $this->money((string) $comped)];
                $total['sales'] = $total['sales']->plus($net);
                $total['cost'] = $total['cost']->plus($cost);
                $total['free'] = $total['free']->plus($comped);
            }
        }

        $overall = $total['sales']->isPositive() ? $total['cost']->multipliedBy('100')->dividedBy($total['sales'], 2, RoundingMode::HalfUp).'%' : '—';

        return [
            'title' => __('Food and beverage cost %'), 'columns' => [__('Outlet'), __('Class'), __('Net sales'), __('Cost of ingredients'), __('Cost %'), __('Complimentary and staff meals (cost)')], 'rows' => $rows,
            'totals' => [__('Total'), '', $this->money((string) $total['sales']), $this->money((string) $total['cost']), $overall, $this->money((string) $total['free'])],
            'summary' => [['label' => __('Net sales'), 'value' => $this->money((string) $total['sales'])], ['label' => __('Cost %'), 'value' => $overall]],
            'note' => __('Sales are settled bills net of discounts and before tax; cost is what the sold recipes took out of stock (dishes with no recipe have no cost here).'),
        ];
    }

    private function money(string $amount): string
    {
        return number_format((float) $amount, 2);
    }
}
