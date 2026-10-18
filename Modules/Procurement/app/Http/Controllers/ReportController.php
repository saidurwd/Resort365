<?php

namespace Modules\Procurement\Http\Controllers;

use App\Support\Reports\CsvExport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Procurement\Http\Controllers\Concerns\ProcurementScreen;
use Modules\Procurement\Http\Requests\ReportRequest;
use Modules\Procurement\Services\Lookups;
use Modules\Procurement\Services\Reports\ProcurementReports;
use Modules\Property\Contracts\PropertyDirectory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Procurement → Reports: open orders, pending receipts, vendor ledger, payables aging, purchase history and price variance
 * for the current property; each also as CSV.
 */
class ReportController extends Controller
{
    use ProcurementScreen;

    public const array REPORTS = ['open-orders', 'pending-receipts', 'vendor-ledger', 'vendor-aging', 'purchase-history', 'price-variance'];

    public function show(ReportRequest $request, string $report, ProcurementReports $reports, PropertyDirectory $properties, Lookups $lookups): View|StreamedResponse|Response
    {
        abort_unless(in_array($report, self::REPORTS, true), 404);
        $propertyId = $this->propertyId();
        $today = CarbonImmutable::parse($properties->find($propertyId)->businessDate ?? now()->toDateString());
        $from = (string) $request->validated('from', $today->startOfMonth()->toDateString());
        $to = (string) $request->validated('to', $today->toDateString());
        $asOf = (string) $request->validated('as_of', $today->toDateString());
        $vendors = $lookups->vendors(false);
        $vendorId = $request->filled('vendor') ? (int) $request->validated('vendor') : null;

        if ($report === 'vendor-ledger' && $vendorId === null && $vendors !== []) {
            $vendorId = array_key_first($vendors);
        }

        $data = match ($report) {
            'open-orders' => $reports->openOrders($propertyId, $today->toDateString()),
            'pending-receipts' => $reports->pendingReceipts($propertyId, $today->toDateString()),
            'vendor-ledger' => $reports->vendorLedger((int) $vendorId, $from, $to),
            'vendor-aging' => $reports->vendorAging($asOf),
            'purchase-history' => $reports->purchaseHistory($propertyId, $from, $to, $vendorId),
            default => $reports->priceVariance($propertyId, $from, $to, $vendorId),
        };

        if ($request->validated('export') === 'csv') {
            return CsvExport::response($data, 'procurement-'.$report.'-'.$today->toDateString().'.csv');
        }

        return view('procurement::reports.show', [
            'report' => $report, 'data' => $data, 'from' => $from, 'to' => $to, 'asOf' => $asOf, 'vendors' => $vendors, 'vendorId' => $vendorId, 'subtitle' => $this->propertyName(),
            'tabs' => ['open-orders' => __('Open orders'), 'pending-receipts' => __('Pending receipts'), 'vendor-ledger' => __('Vendor ledger'), 'vendor-aging' => __('Payables aging'), 'purchase-history' => __('Purchase history'), 'price-variance' => __('Price variance')],
            'withDates' => in_array($report, ['vendor-ledger', 'purchase-history', 'price-variance'], true), 'withVendor' => in_array($report, ['vendor-ledger', 'purchase-history', 'price-variance'], true),
        ]);
    }
}
