<?php

namespace Modules\HR\Http\Controllers;

use App\Support\Reports\CsvExport;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\HR\Http\Controllers\Concerns\HrScreen;
use Modules\HR\Http\Requests\AttendanceReportRequest;
use Modules\HR\Models\Attendance;
use Modules\HR\Services\AttendanceReports;
use Modules\Property\Contracts\DepartmentDirectory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HR → Attendance → Monthly report: lateness, early leave, overtime and absences per employee.
 */
class AttendanceReportController extends Controller
{
    use HrScreen;

    public function show(AttendanceReportRequest $request, AttendanceReports $reports, DepartmentDirectory $departments): View|StreamedResponse|Response
    {
        Gate::authorize('viewAny', Attendance::class);
        $today = $this->today();
        $month = (string) $request->validated('month', substr($today, 0, 7));
        $department = $request->filled('department') ? (int) $request->validated('department') : null;
        $data = $reports->monthly($this->propertyId(), $month, $department, $today);

        if ($request->validated('export') === 'csv') {
            return CsvExport::response($data, 'attendance-'.$month.'.csv');
        }

        return view('hr::attendance.report', ['data' => $data, 'month' => $month, 'department' => $department, 'departments' => collect($departments->all())->pluck('name', 'id')->all(), 'subtitle' => $this->propertyName()]);
    }
}
