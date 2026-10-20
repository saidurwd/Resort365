<?php

namespace Modules\HR\Services;

use Carbon\CarbonImmutable;
use Modules\HR\Enums\AttendanceStatus;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Roster;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * HR → Attendance report (Step 6.2): a month per employee with the days scheduled and attended, absences, late days and
 * minutes, early leave and overtime; and (Step 6.6) the same added up by department. Answers the array the report table and
 * CSV download use.
 */
class AttendanceReports
{
    private const array KEYS = ['scheduled', 'present', 'absent', 'leave', 'late_days', 'late', 'early', 'overtime', 'worked'];

    public function __construct(
        private readonly ScheduleLookup $schedule,
        private readonly DepartmentDirectory $departments,
    ) {}

    /**
     * Absences are the scheduled days (not holidays, up to today) with no attendance record, plus days marked absent. A day
     * of approved leave is no longer scheduled (the leave replaced its shift on the roster), so it is not an absence.
     *
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function monthly(int $propertyId, string $month, ?int $departmentId, string $today): array
    {
        $start = CarbonImmutable::parse($month.'-01');
        $names = collect($this->departments->all(false))->pluck('name', 'id');
        $rows = [];
        $sum = array_fill_keys(self::KEYS, 0);

        foreach ($this->figures($propertyId, $start, $departmentId, $today) as $line) {
            $employee = $line['employee'];
            $figures = $line['figures'];

            foreach ($figures as $key => $value) {
                $sum[$key] += $value;
            }

            $rows[] = [$employee->employee_no.' · '.$employee->full_name, $names[$employee->department_id] ?? '—', (string) $figures['scheduled'], (string) $figures['present'], (string) $figures['absent'], (string) $figures['leave'], (string) $figures['late_days'],
                (string) $figures['late'], (string) $figures['early'], $this->hours($figures['overtime']), $this->hours($figures['worked'])];
        }

        return [
            'title' => __('Attendance for :month', ['month' => $start->format('F Y')]),
            'columns' => [__('Employee'), __('Department'), __('Scheduled days'), __('Present'), __('Absent'), __('On leave'), __('Late days'), __('Late (min)'), __('Early leave (min)'), __('Overtime (h:mm)'), __('Worked (h:mm)')],
            'rows' => $rows,
            'totals' => [__('Total'), '', (string) $sum['scheduled'], (string) $sum['present'], (string) $sum['absent'], (string) $sum['leave'], (string) $sum['late_days'], (string) $sum['late'], (string) $sum['early'], $this->hours($sum['overtime']), $this->hours($sum['worked'])],
            'summary' => [['label' => __('Late days'), 'value' => (string) $sum['late_days']], ['label' => __('Late minutes'), 'value' => (string) $sum['late']], ['label' => __('Overtime'), 'value' => $this->hours($sum['overtime'])], ['label' => __('Absent days'), 'value' => (string) $sum['absent']]],
            'note' => __('Late counts every minute from the start of the shift once the grace time is passed. Overtime is time after the end of the shift (and all time worked on holidays and days off). Absent is a scheduled day with no record; days of approved leave are shown apart and are not absences.'),
        ];
    }

    /**
     * The month's attendance added up by department, with the share of scheduled days attended.
     *
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function byDepartment(int $propertyId, string $month, string $today): array
    {
        $start = CarbonImmutable::parse($month.'-01');
        $names = collect($this->departments->all(false))->pluck('name', 'id');
        $groups = [];
        $sum = array_fill_keys(self::KEYS, 0);
        $people = 0;

        foreach ($this->figures($propertyId, $start, null, $today) as $line) {
            $key = $names[$line['employee']->department_id] ?? '—';
            $groups[$key] ??= ['people' => 0, ...array_fill_keys(self::KEYS, 0)];
            $groups[$key]['people']++;
            $people++;

            foreach ($line['figures'] as $name => $value) {
                $groups[$key][$name] += $value;
                $sum[$name] += $value;
            }
        }

        ksort($groups);
        $rows = [];

        foreach ($groups as $department => $g) {
            $rows[] = [(string) $department, (string) $g['people'], (string) $g['scheduled'], (string) $g['present'], (string) $g['absent'], (string) $g['leave'], $this->percent($g['present'], $g['scheduled']), (string) $g['late_days'], $this->hours($g['overtime']), $this->hours($g['worked'])];
        }

        return [
            'title' => __('Attendance summary for :month', ['month' => $start->format('F Y')]),
            'columns' => [__('Department'), __('Employees'), __('Scheduled days'), __('Present'), __('Absent'), __('On leave'), __('Attendance'), __('Late days'), __('Overtime (h:mm)'), __('Worked (h:mm)')],
            'rows' => $rows,
            'totals' => [__('Total'), (string) $people, (string) $sum['scheduled'], (string) $sum['present'], (string) $sum['absent'], (string) $sum['leave'], $this->percent($sum['present'], $sum['scheduled']), (string) $sum['late_days'], $this->hours($sum['overtime']), $this->hours($sum['worked'])],
            'summary' => [['label' => __('Employees'), 'value' => (string) $people], ['label' => __('Attendance'), 'value' => $this->percent($sum['present'], $sum['scheduled'])], ['label' => __('Absent days'), 'value' => (string) $sum['absent']], ['label' => __('Overtime'), 'value' => $this->hours($sum['overtime'])]],
            'note' => __('Attendance is the days present as a share of the days scheduled up to today. The figures are the attendance report\'s, added up by department.'),
        ];
    }

    /**
     * Every employee of the month with their figures.
     *
     * @return list<array{employee: Employee, figures: array<string, int>}>
     */
    private function figures(int $propertyId, CarbonImmutable $start, ?int $departmentId, string $today): array
    {
        $from = $start->toDateString();
        $to = $start->endOfMonth()->toDateString();
        $upTo = min($to, $today);
        $employees = Employee::query()->where('property_id', $propertyId)->whereDate('joined_on', '<=', $to)->where(fn ($query) => $query->whereNull('exited_on')->orWhereDate('exited_on', '>=', $from))
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))->orderBy('first_name')->orderBy('last_name')->get();
        $ids = $employees->pluck('id')->all();
        $holidays = $this->schedule->holidays($propertyId, $from, $to);
        $attendance = Attendance::query()->whereIn('employee_id', $ids)->whereBetween('work_date', [$from, $to])->get()->groupBy('employee_id');
        $rostered = Roster::query()->whereIn('employee_id', $ids)->whereBetween('work_date', [$from, $upTo])->whereNotNull('shift_id')->get()->groupBy('employee_id');
        $leaveDays = Roster::query()->whereIn('employee_id', $ids)->whereBetween('work_date', [$from, $to])->whereNotNull('leave_request_id')->get()->countBy('employee_id');
        $lines = [];

        foreach ($employees as $employee) {
            $records = $attendance->get($employee->id, collect());
            $scheduled = $rostered->get($employee->id, collect())->map(fn (Roster $roster): string => $roster->work_date->toDateString())->reject(fn (string $date): bool => isset($holidays[$date]))->values();
            $recorded = $records->keyBy(fn (Attendance $record): string => $record->work_date->toDateString());
            $present = $records->filter(fn (Attendance $record): bool => ! in_array($record->status, [AttendanceStatus::Absent, AttendanceStatus::Leave], true) && ($record->clock_in !== null || $record->clock_out !== null))->count();
            $absent = $scheduled->filter(fn (string $date): bool => ! isset($recorded[$date]))->count() + $records->where('status', AttendanceStatus::Absent)->count();
            $lines[] = ['employee' => $employee, 'figures' => [
                'scheduled' => $scheduled->count(), 'present' => $present, 'absent' => $absent, 'leave' => (int) $leaveDays->get($employee->id, 0), 'late_days' => $records->where('late_minutes', '>', 0)->count(), 'late' => (int) $records->sum('late_minutes'),
                'early' => (int) $records->sum('early_leave_minutes'), 'overtime' => (int) $records->sum('overtime_minutes'), 'worked' => (int) $records->sum('worked_minutes'),
            ]];
        }

        return $lines;
    }

    private function hours(int $minutes): string
    {
        return intdiv($minutes, 60).':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }

    private function percent(int $part, int $whole): string
    {
        return $whole > 0 ? number_format($part * 100 / $whole, 1).'%' : '—';
    }
}
