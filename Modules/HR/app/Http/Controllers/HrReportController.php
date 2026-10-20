<?php

namespace Modules\HR\Http\Controllers;

use App\Support\Reports\CsvExport;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\HR\Http\Controllers\Concerns\HrScreen;
use Modules\HR\Http\Requests\HrReportRequest;
use Modules\HR\Services\AttendanceReports;
use Modules\HR\Services\HrReports;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HR → Reports: headcount, attendance summary by department and leave balances by department, for the current property.
 */
class HrReportController extends Controller
{
    use HrScreen;

    public function index(): View
    {
        return view('hr::reports.index', ['subtitle' => $this->propertyName()]);
    }

    public function headcount(HrReportRequest $request, HrReports $reports): View|StreamedResponse
    {
        $to = (string) $request->validated('to', $this->today());
        $from = (string) $request->validated('from', CarbonImmutable::parse($to)->startOfMonth()->toDateString());
        $data = $reports->headcount($this->propertyId(), $from, $to);

        return $this->respond($request, $data, 'headcount-'.$to, 'headcount', ['from' => $from, 'to' => $to]);
    }

    public function attendance(HrReportRequest $request, AttendanceReports $reports): View|StreamedResponse
    {
        $month = (string) $request->validated('month', substr($this->today(), 0, 7));
        $data = $reports->byDepartment($this->propertyId(), $month, $this->today());

        return $this->respond($request, $data, 'attendance-summary-'.$month, 'attendance', ['month' => $month]);
    }

    public function leave(HrReportRequest $request, HrReports $reports): View|StreamedResponse
    {
        $year = (int) $request->validated('year', (int) substr($this->today(), 0, 4));
        $data = $reports->leaveBalances($this->propertyId(), $year);

        return $this->respond($request, $data, 'leave-balances-'.$year, 'leave', ['year' => $year]);
    }

    /**
     * @param  array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}  $data
     * @param  array<string, string|int>  $filters
     */
    private function respond(HrReportRequest $request, array $data, string $file, string $report, array $filters): View|StreamedResponse
    {
        if ($request->validated('export') === 'csv') {
            return CsvExport::response($data, $file.'.csv');
        }

        return view('hr::reports.show', ['data' => $data, 'report' => $report, 'filters' => $filters, 'subtitle' => $this->propertyName()]);
    }
}
