<?php

namespace Modules\Inventory\Http\Controllers;

use App\Support\Reports\CsvExport;
use App\Support\Tenancy\PropertyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Inventory\Http\Requests\ReportRequest;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Services\Reports\InventoryReports;
use Modules\Property\Contracts\PropertyDirectory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Inventory → Reports: stock valuation, consumption by department, slow-moving stock, and theoretical against actual use
 * for the current property; each also as CSV.
 */
class ReportController extends Controller
{
    public const array REPORTS = ['valuation', 'departments', 'slow-moving', 'usage'];

    public function show(ReportRequest $request, string $report, InventoryReports $reports, PropertyDirectory $properties): View|StreamedResponse|Response
    {
        abort_unless(in_array($report, self::REPORTS, true), 404);
        $propertyId = app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
        $today = CarbonImmutable::parse($properties->find($propertyId)->businessDate ?? now()->toDateString());
        $from = (string) $request->validated('from', $today->startOfMonth()->toDateString());
        $to = (string) $request->validated('to', $today->toDateString());
        $storeId = $request->filled('store') ? (int) $request->validated('store') : null;
        $asOf = $request->filled('as_of') ? (string) $request->validated('as_of') : null;
        $days = (int) ($request->validated('days') ?? 60);
        $stores = Store::query()->where('property_id', $propertyId)->orderBy('name')->pluck('name', 'id')->all();

        if ($report === 'usage' && $storeId === null && $stores !== []) {
            $storeId = array_key_first($stores);
        }

        $data = match ($report) {
            'valuation' => $reports->valuation($propertyId, $asOf, $storeId),
            'departments' => $reports->departments($propertyId, $from, $to, $storeId),
            'slow-moving' => $reports->slowMoving($propertyId, $days, $storeId, $today->toDateString()),
            default => $reports->theoreticalVsActual($propertyId, $from, $to, $storeId),
        };

        if ($request->validated('export') === 'csv') {
            return CsvExport::response($data, 'inventory-'.$report.'-'.$today->toDateString().'.csv');
        }

        return view('inventory::reports.show', [
            'report' => $report, 'data' => $data, 'from' => $from, 'to' => $to, 'asOf' => $asOf, 'days' => $days, 'stores' => $stores, 'storeId' => $storeId,
            'tabs' => ['valuation' => __('Stock valuation'), 'departments' => __('By department'), 'slow-moving' => __('Slow-moving'), 'usage' => __('Theoretical vs actual')],
            'canReconcile' => $request->user()?->can('accounting.report.view') ?? false,
        ]);
    }
}
