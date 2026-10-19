<?php

namespace Modules\HR\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\HR\Actions\DeleteHoliday;
use Modules\HR\Actions\SaveHoliday;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Http\Controllers\Concerns\HrScreen;
use Modules\HR\Http\Requests\HolidayRequest;
use Modules\HR\Models\Holiday;
use Modules\HR\Services\ScheduleLookup;

/**
 * HR → Holidays of the current property.
 */
class HolidayController extends Controller
{
    use HrScreen;

    public function index(Request $request, ScheduleLookup $schedule): View
    {
        Gate::authorize('viewAny', Holiday::class);
        $year = $request->integer('year', (int) CarbonImmutable::parse($this->today())->year);
        $propertyId = $this->propertyId();
        $holidays = $schedule->holidays($propertyId, $year.'-01-01', $year.'-12-31');

        return view('hr::holidays.index', [
            'year' => $year, 'holidays' => $holidays, 'canManage' => $request->user()?->can('hr.holiday.manage') ?? false, 'subtitle' => $this->propertyName(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Holiday::class);

        return view('hr::holidays.form', ['record' => null, 'subtitle' => $this->propertyName()]);
    }

    public function store(HolidayRequest $request, SaveHoliday $save): RedirectResponse
    {
        Gate::authorize('create', Holiday::class);

        try {
            $holiday = $save->handle(null, $request->validated() + ['property_id' => $this->propertyId()]);
        } catch (HrRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('hr.holidays.index', ['year' => $holiday->holiday_date->year])->with('success', __('Holiday ":name" added.', ['name' => $holiday->name]));
    }

    public function edit(Holiday $holiday): View
    {
        Gate::authorize('update', $holiday);

        return view('hr::holidays.form', ['record' => $holiday, 'subtitle' => $this->propertyName()]);
    }

    public function update(HolidayRequest $request, Holiday $holiday, SaveHoliday $save): RedirectResponse
    {
        Gate::authorize('update', $holiday);

        try {
            $save->handle($holiday, $request->validated());
        } catch (HrRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('hr.holidays.index', ['year' => $holiday->holiday_date->year])->with('success', __('Holiday ":name" saved.', ['name' => $holiday->name]));
    }

    public function destroy(Holiday $holiday, DeleteHoliday $delete): RedirectResponse
    {
        Gate::authorize('delete', $holiday);
        $delete->handle($holiday);

        return to_route('hr.holidays.index')->with('success', __('Holiday ":name" deleted.', ['name' => $holiday->name]));
    }
}
