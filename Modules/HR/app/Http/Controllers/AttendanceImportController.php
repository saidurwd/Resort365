<?php

namespace Modules\HR\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\HR\Actions\ImportAttendance;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Http\Controllers\Concerns\HrScreen;
use Modules\HR\Http\Requests\AttendanceImportRequest;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\AttendanceImport;

/**
 * HR → Attendance → Import: upload a biometric CSV, and the imports so far.
 */
class AttendanceImportController extends Controller
{
    use HrScreen;

    public function create(): View
    {
        Gate::authorize('import', Attendance::class);

        return view('hr::attendance.import', ['imports' => AttendanceImport::query()->where('property_id', $this->propertyId())->latest('id')->limit(20)->get(), 'subtitle' => $this->propertyName()]);
    }

    public function store(AttendanceImportRequest $request, ImportAttendance $import): RedirectResponse
    {
        Gate::authorize('import', Attendance::class);
        $file = $request->file('file');

        try {
            $result = $import->handle($this->propertyId(), (string) $file?->getClientOriginalName(), (string) file_get_contents((string) $file?->getRealPath()), $request->user()?->id);
        } catch (HrRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('hr.attendance.import')->with('success', trans_choice(':count day imported.|:count days imported.', $result['days']).($result['skipped'] > 0 ? ' '.trans_choice(':count day was skipped (entered by hand, or outside the employee\'s employment).|:count days were skipped (entered by hand, or outside the employee\'s employment).', $result['skipped']) : ''));
    }
}
