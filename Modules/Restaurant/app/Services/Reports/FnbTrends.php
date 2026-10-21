<?php

namespace Modules\Restaurant\Services\Reports;

use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Restaurant\Enums\BillStatus;

/**
 * The F&B trends report (Step 7.2) for a property, a range of business dates and some outlets: net sales (after discounts, before
 * tax; bills nobody paid for are left out), covers and bills per day, added up by the pure `TrendBuilder`. It reads the equal range
 * before too, to compare with.
 *
 * @phpstan-type Report array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
 */
class FnbTrends
{
    public function __construct(
        private readonly TrendBuilder $trends,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  list<int>  $outletIds
     * @return array{table: Report, chart: array<string, mixed>}
     */
    public function build(int $propertyId, string $from, string $to, array $outletIds, string $grain): array
    {
        $length = (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1;
        $earliest = CarbonImmutable::parse($from)->subDays($length)->toDateString();
        $result = $this->trends->build($this->days($propertyId, $earliest, $to, $outletIds), $from, $to, $grain);
        $money = fn (string $value): string => number_format((float) $value, 2);
        $rows = array_map(fn (array $row): array => [$row['label'], $money($row['sales']), (string) $row['covers'], (string) $row['bills'], $money($row['per_cover']), $row['change'] === null ? '—' : ($row['change'] > 0 ? '+' : '').$row['change'].'%'], $result['rows']);
        $totals = $result['totals'];

        return [
            'table' => [
                'title' => __('F&B trends'), 'columns' => [match ($grain) {
                    'week' => __('Week'), 'month' => __('Month'), default => __('Day')
                }, __('Net sales'), __('Covers'), __('Bills'), __('Spend per cover'), __('Change')],
                'rows' => $rows, 'totals' => [__('Total'), $money($totals['sales']), (string) $totals['covers'], (string) $totals['bills'], $money($totals['per_cover']), $totals['change'] === null ? '—' : ($totals['change'] > 0 ? '+' : '').$totals['change'].'%'],
                'summary' => [
                    ['label' => __('Net sales'), 'value' => $money($totals['sales'])], ['label' => __('Previous period'), 'value' => $money($totals['previous_sales'])],
                    ['label' => __('Change'), 'value' => $totals['change'] === null ? '—' : ($totals['change'] > 0 ? '+' : '').$totals['change'].'%'], ['label' => __('Covers'), 'value' => (string) $totals['covers']],
                    ['label' => __('Spend per cover'), 'value' => $money($totals['per_cover'])], ['label' => __('Best'), 'value' => $result['best'] !== null ? $result['best']['label'] : '—'],
                ],
                'note' => __('Net sales are after discounts and before tax, from settled bills that were paid for (complimentary bills and staff meals are left out). Change compares each row with the one before, and the total with the equal period before the range.'),
            ],
            'chart' => [
                'type' => 'bar',
                'data' => ['labels' => array_column($result['rows'], 'label'), 'datasets' => [
                    ['type' => 'bar', 'label' => __('Net sales'), 'data' => array_map(fn (array $row): float => (float) $row['sales'], $result['rows']), 'backgroundColor' => 'rgba(13,110,253,.45)'],
                    ['type' => 'line', 'label' => __('Spend per cover'), 'data' => array_map(fn (array $row): float => (float) $row['per_cover'], $result['rows']), 'yAxisID' => 'y1', 'borderColor' => '#198754', 'tension' => 0.25],
                ]],
                'options' => ['scales' => ['y1' => ['position' => 'right', 'min' => 0, 'grid' => ['drawOnChartArea' => false]]]],
            ],
        ];
    }

    /**
     * @param  list<int>  $outletIds
     * @return array<string, array{sales: string, covers: int, bills: int}>
     */
    private function days(int $propertyId, string $from, string $to, array $outletIds): array
    {
        $tenantId = $this->tenant->tenantOrFail(self::class)->id;
        $paid = fn (string $alias) => DB::table('pos_bills as '.$alias)->where($alias.'.tenant_id', $tenantId)->where($alias.'.property_id', $propertyId)->where($alias.'.status', BillStatus::Settled->value)->where($alias.'.is_complimentary', false)
            ->whereBetween($alias.'.business_date', [$from, $to])->whereIn($alias.'.outlet_id', $outletIds);
        $days = [];

        foreach ($paid('b')->join('pos_bill_lines as bl', 'bl.pos_bill_id', '=', 'b.id')->groupBy('b.business_date')->get(['b.business_date', DB::raw('SUM(bl.amount - bl.discount) as sales')]) as $row) {
            $date = substr((string) $row->business_date, 0, 10);
            $days[$date] ??= ['sales' => '0.00', 'covers' => 0, 'bills' => 0];
            $days[$date]['sales'] = bcadd((string) $row->sales, '0', 2);
        }

        foreach ($paid('b')->groupBy('b.business_date')->get(['b.business_date', DB::raw('COUNT(*) as bills')]) as $row) {
            $date = substr((string) $row->business_date, 0, 10);
            $days[$date] ??= ['sales' => '0.00', 'covers' => 0, 'bills' => 0];
            $days[$date]['bills'] = (int) $row->bills;
        }

        $orders = $paid('b')->select('b.pos_order_id')->distinct();

        foreach (DB::table('pos_orders as o')->whereIn('o.id', $orders)->groupBy('o.business_date')->get(['o.business_date', DB::raw('SUM(o.covers) as covers')]) as $row) {
            $date = substr((string) $row->business_date, 0, 10);
            $days[$date] ??= ['sales' => '0.00', 'covers' => 0, 'bills' => 0];
            $days[$date]['covers'] = (int) $row->covers;
        }

        return $days;
    }
}
