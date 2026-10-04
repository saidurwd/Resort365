<?php

namespace Modules\FrontOffice\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\FrontOffice\Http\Requests\FlashReportRequest;
use Modules\FrontOffice\Services\FlashReport;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Front Office → Flash report: one business date's rooms, revenue and takings. Authorized by the
 * frontoffice.report.view route middleware and FlashReportRequest.
 */
class FlashReportController extends Controller
{
    public function index(FlashReportRequest $request, FlashReport $report, PropertyDirectory $properties): View
    {
        $propertyId = app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
        $property = $properties->find($propertyId);
        $businessDate = $property->businessDate ?? now()->toDateString();
        $date = (string) ($request->validated('date') ?? CarbonImmutable::parse($businessDate)->subDay()->toDateString());

        return view('frontoffice::reports.flash', [
            'property' => $property,
            'date' => CarbonImmutable::parse($date),
            'businessDate' => $businessDate,
            'report' => $report->for($propertyId, $date, $businessDate),
            'print' => $request->boolean('print'),
        ]);
    }
}
