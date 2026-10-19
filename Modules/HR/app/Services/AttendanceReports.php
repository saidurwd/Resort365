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
 * minutes, early leave and overtime. Answers the array the report table and CSV download use.
 */
class AttendanceReports
{
    public function __construct(
        private readonly ScheduleLookup $schedule,
        private readonly DepartmentDirectory $departments,
    ) {}

    /**
     * Absences are the scheduled days (not holidays, up to today) with no attendance record, plus days marked absent.
     * TODO(step-6.3): leave days will not count as absences.
     *
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function monthly(int $propertyId, string $month, ?int $departmentId, string $today): array
    {
        $start = CarbonImmutable::parse($month.'-01');
        $from = $start->toDateString();
        $to = $start->endOfMonth()->toDateString();
        $upTo = min($to, $today);
        $employees = Employee::query()->where('property_id', $propertyId)->whereDate('joined_on', '<=', $to)->where(fn ($query) => $query->whereNull('exited_on')->orWhereDate('exited_on', '>=', $from))
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))->orderBy('first_name')->orderBy('last_name')->get();
        $ids = $employees->pluck('id')->all();
        $holidays = $this->schedule->holidays($propertyId, $from, $to);
        $names = collect($this->departments->all(false))->pluck('name', 'id');
        $attendance = Attendance::query()->whereIn('employee_id', $ids)->whereBetween('work_date', [$from, $to])->get()->groupBy('employee_id');
        $rostered = Roster::query()->whereIn('employee_id', $ids)->whereBetween('work_date', [$from, $upTo])->whereNotNull('shift_id')->get()->groupBy('employee_id');
        $rows = [];
        $sum = ['scheduled' => 0, 'present' => 0, 'absent' => 0, 'late_days' => 0, 'late' => 0, 'early' => 0, 'overtime' => 0, 'worked' => 0];

        foreach ($employees as $employee) {
            $records = $attendance->get($employee->id, collect());
            $scheduled = $rostered->get($employee->id, collect())->map(fn (Roster $roster): string => $roster->work_date->toDateString())->reject(fn (string $date): bool => isset($holidays[$date]))->values();
            $recorded = $records->keyBy(fn (Attendance $record): string => $record->work_date->toDateString());
            $present = $records->filter(fn (Attendance $record): bool => ! in_array($record->status, [AttendanceStatus::Absent, AttendanceStatus::Leave], true) && ($record->clock_in !== null || $record->clock_out !== null))->count();
            $absent = $scheduled->filter(fn (string $date): bool => ! isset($recorded[$date]))->count() + $records->where('status', AttendanceStatus::Absent)->count();
            $figures = [
                'scheduled' => $scheduled->count(), 'present' => $present, 'absent' => $absent, 'late_days' => $records->where('late_minutes', '>', 0)->count(), 'late' => (int) $records->sum('late_minutes'),
                'early' => (int) $records->sum('early_leave_minutes'), 'overtime' => (int) $records->sum('overtime_minutes'), 'worked' => (int) $records->sum('worked_minutes'),
            ];

            foreach ($figures as $key => $value) {
                $sum[$key] += $value;
            }

            $rows[] = [$employee->employee_no.' · '.$employee->full_name, $names[$employee->department_id] ?? '—', (string) $figures['scheduled'], (string) $figures['present'], (string) $figures['absent'], (string) $figures['late_days'],
                (string) $figures['late'], (string) $figures['early'], $this->hours($figures['overtime']), $this->hours($figures['worked'])];
        }

        return [
            'title' => __('Attendance for :month', ['month' => $start->format('F Y')]),
            'columns' => [__('Employee'), __('Department'), __('Scheduled days'), __('Present'), __('Absent'), __('Late days'), __('Late (min)'), __('Early leave (min)'), __('Overtime (h:mm)'), __('Worked (h:mm)')],
            'rows' => $rows,
            'totals' => [__('Total'), '', (string) $sum['scheduled'], (string) $sum['present'], (string) $sum['absent'], (string) $sum['late_days'], (string) $sum['late'], (string) $sum['early'], $this->hours($sum['overtime']), $this->hours($sum['worked'])],
            'summary' => [['label' => __('Late days'), 'value' => (string) $sum['late_days']], ['label' => __('Late minutes'), 'value' => (string) $sum['late']], ['label' => __('Overtime'), 'value' => $this->hours($sum['overtime'])], ['label' => __('Absent days'), 'value' => (string) $sum['absent']]],
            'note' => __('Late counts every minute from the start of the shift once the grace time is passed. Overtime is time after the end of the shift (and all time worked on holidays and days off). Absent is a scheduled day with no record.'),
        ];
    }

    private function hours(int $minutes): string
    {
        return intdiv($minutes, 60).':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
}
