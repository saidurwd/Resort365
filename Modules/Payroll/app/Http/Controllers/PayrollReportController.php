<?php

namespace Modules\Payroll\Http\Controllers;

use App\Support\Reports\CsvExport;
use App\Support\Tenancy\PropertyContext;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Payroll\Http\Requests\PayrollReportRequest;
use Modules\Payroll\Services\PayrollCostReports;
use Modules\Property\Contracts\PropertyDirectory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Payroll → Reports: payroll cost by department, the festival bonus register, and service charge and tips, for the current property.
 */
class PayrollReportController extends Controller
{
    public function __construct(
        private readonly PropertyContext $property,
        private readonly PropertyDirectory $properties,
    ) {}

    public function index(): View
    {
        return view('payroll::reports.index', ['subtitle' => $this->properties->find($this->propertyId())->name ?? null]);
    }

    public function cost(PayrollReportRequest $request, PayrollCostReports $reports): View|StreamedResponse
    {
        $to = (string) $request->validated('to', substr($this->today(), 0, 7));
        $from = (string) $request->validated('from', CarbonImmutable::parse($to.'-01')->subMonths(2)->format('Y-m'));

        return $this->respond($request, $reports->costByDepartment($this->propertyId(), $from, $to), 'payroll-cost-'.$from.'-'.$to, 'cost', ['from' => $from, 'to' => $to]);
    }

    public function festival(PayrollReportRequest $request, PayrollCostReports $reports): View|StreamedResponse
    {
        $year = (int) $request->validated('year', (int) substr($this->today(), 0, 4));

        return $this->respond($request, $reports->festivalBonuses($this->propertyId(), $year), 'festival-bonuses-'.$year, 'festival', ['year' => $year]);
    }

    public function pools(PayrollReportRequest $request, PayrollCostReports $reports): View|StreamedResponse
    {
        $year = (int) $request->validated('year', (int) substr($this->today(), 0, 4));

        return $this->respond($request, $reports->pools($this->propertyId(), $year), 'service-charge-tips-'.$year, 'pools', ['year' => $year]);
    }

    /**
     * @param  array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}  $data
     * @param  array<string, string|int>  $filters
     */
    private function respond(PayrollReportRequest $request, array $data, string $file, string $report, array $filters): View|StreamedResponse
    {
        if ($request->validated('export') === 'csv') {
            return CsvExport::response($data, $file.'.csv');
        }

        return view('payroll::reports.show', ['data' => $data, 'report' => $report, 'filters' => $filters, 'subtitle' => $this->properties->find($this->propertyId())->name ?? null]);
    }

    private function today(): string
    {
        return $this->properties->find($this->propertyId())->businessDate ?? now()->toDateString();
    }

    private function propertyId(): int
    {
        return $this->property->currentId() ?? abort(403, __('Choose a property first.'));
    }
}
