<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Http\Requests\RestaurantReportRequest;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Services\OutletAccess;
use Modules\Restaurant\Services\Reports\RestaurantReports;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Restaurant → Reports (ARCHITECTURE §5.10.14): sales, exceptions, room charges, meal plans, POS sessions
 * and table turnover for the current property, a range of business dates and an outlet; each also as CSV.
 */
class RestaurantReportController extends Controller
{
    public const array REPORTS = ['sales', 'exceptions', 'room-charges', 'meal-plans', 'sessions', 'turnover'];

    public function show(RestaurantReportRequest $request, string $report, RestaurantReports $reports, PropertyDirectory $properties, OutletAccess $access): View|StreamedResponse|Response
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

        $data = match ($report) {
            'sales' => $reports->sales($propertyId, $from, $to, $outletId, $by),
            'exceptions' => $reports->exceptions($propertyId, $from, $to, $outletId),
            'room-charges' => $reports->roomCharges($propertyId, $from, $to, $outletId),
            'meal-plans' => $reports->mealPlans($propertyId, $from, $to, $outletId),
            'sessions' => $reports->sessions($propertyId, $from, $to, $outletId),
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
            'tabs' => ['sales' => __('Sales'), 'exceptions' => __('Exceptions'), 'room-charges' => __('Room charges'), 'meal-plans' => __('Meal plans'), 'sessions' => __('POS sessions'), 'turnover' => __('Table turnover')],
            'currency' => $property->currencyCode ?? '',
            'saleBy' => ['outlet' => __('Outlet'), 'category' => __('Category'), 'item' => __('Item'), 'hour' => __('Hour'), 'waiter' => __('Waiter'), 'method' => __('Payment method')],
        ]);
    }
}
