<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Http\Requests\RestaurantReportRequest;
use Modules\Restaurant\Models\MenuCategory;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Services\OutletAccess;
use Modules\Restaurant\Services\Reports\CostReports;
use Modules\Restaurant\Services\Reports\FnbTrends;
use Modules\Restaurant\Services\Reports\MenuEngineeringReport;
use Modules\Restaurant\Services\Reports\RestaurantReports;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Restaurant → Reports (ARCHITECTURE §5.10.14): sales, exceptions, room charges, meal plans, POS sessions
 * and table turnover for the current property, a range of business dates and an outlet; each also as CSV.
 */
class RestaurantReportController extends Controller
{
    public const array REPORTS = ['sales', 'exceptions', 'room-charges', 'meal-plans', 'sessions', 'turnover', 'food-cost', 'menu-engineering', 'trends'];

    public function show(RestaurantReportRequest $request, string $report, RestaurantReports $reports, CostReports $costs, MenuEngineeringReport $engineering, FnbTrends $trends, PropertyDirectory $properties, OutletAccess $access): View|StreamedResponse|Response
    {
        abort_unless(in_array($report, self::REPORTS, true), 404);
        $propertyId = app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
        $property = $properties->find($propertyId);
        $today = CarbonImmutable::parse($property->businessDate ?? now()->toDateString());
        $from = (string) $request->validated('from', $today->subDay()->toDateString());
        $to = (string) $request->validated('to', $today->subDay()->toDateString());
        $ids = $access->outletIds((int) $request->user()?->getAuthIdentifier());
        $outlets = Outlet::query()->where('property_id', $propertyId)->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))->orderBy('sort_order')->orderBy('name')->get();
        $outletId = $request->filled('outlet') && $outlets->contains('id', $request->integer('outlet')) ? $request->integer('outlet') : null;
        $by = (string) $request->validated('by', 'outlet');
        $grain = (string) $request->validated('grain', 'week');
        $category = $request->filled('category') ? (int) $request->validated('category') : null;
        $chart = null;
        $unknown = [];
        $scopeIds = $outletId !== null ? [$outletId] : $outlets->pluck('id')->all();

        if (in_array($report, ['menu-engineering', 'trends'], true) && ! $request->filled('from')) {
            // These look over weeks, not at yesterday.
            $from = $today->subDays($report === 'trends' ? 83 : 29)->toDateString();
            $to = $today->subDay()->toDateString();
        }

        $data = match ($report) {
            'sales' => $reports->sales($propertyId, $from, $to, $outletId, $by),
            'exceptions' => $reports->exceptions($propertyId, $from, $to, $outletId),
            'room-charges' => $reports->roomCharges($propertyId, $from, $to, $outletId),
            'meal-plans' => $reports->mealPlans($propertyId, $from, $to, $outletId),
            'sessions' => $reports->sessions($propertyId, $from, $to, $outletId),
            'food-cost' => $costs->foodCost($propertyId, $from, $to, $outletId),
            'menu-engineering' => (function () use ($engineering, $propertyId, $from, $to, $scopeIds, $category, &$chart, &$unknown): array {
                $built = $engineering->build($propertyId, $from, $to, $scopeIds, $category);
                $chart = $built['chart'];
                $unknown = $built['unknown'];

                return $built['table'];
            })(),
            'trends' => (function () use ($trends, $propertyId, $from, $to, $scopeIds, $grain, &$chart): array {
                $built = $trends->build($propertyId, $from, $to, $scopeIds, $grain);
                $chart = $built['chart'];

                return $built['table'];
            })(),
            default => $reports->turnover($propertyId, $from, $to, $outletId),
        };

        if ($request->validated('export') === 'csv') {
            return response()->streamDownload(function () use ($data): void {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $data['columns'], escape: '\\');

                foreach ($data['rows'] as $row) {
                    fputcsv($out, $row, escape: '\\');
                }

                if ($data['totals'] !== null) {
                    fputcsv($out, $data['totals'], escape: '\\');
                }

                fclose($out);
            }, 'restaurant-'.$report.'-'.$from.'-'.$to.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('restaurant::reports.show', [
            'report' => $report, 'data' => $data, 'from' => $from, 'to' => $to, 'by' => $by, 'outlets' => $outlets, 'outletId' => $outletId,
            'tabs' => ['sales' => __('Sales'), 'exceptions' => __('Exceptions'), 'room-charges' => __('Room charges'), 'meal-plans' => __('Meal plans'), 'sessions' => __('POS sessions'), 'turnover' => __('Table turnover'), 'food-cost' => __('Food cost %'), 'menu-engineering' => __('Menu engineering'), 'trends' => __('Trends')],
            'chart' => $chart, 'unknown' => $unknown, 'grain' => $grain, 'category' => $category,
            'categories' => MenuCategory::query()->where('property_id', $propertyId)->orderBy('sort_order')->get()->mapWithKeys(fn (MenuCategory $c): array => [$c->id => $c->translated('name')])->all(),
            'currency' => $property->currencyCode ?? '',
            'saleBy' => ['outlet' => __('Outlet'), 'category' => __('Category'), 'item' => __('Item'), 'hour' => __('Hour'), 'waiter' => __('Waiter'), 'method' => __('Payment method')],
        ]);
    }
}
