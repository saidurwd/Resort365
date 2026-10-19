<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\HR\Enums\AttendanceSource;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\AttendanceImport;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Shift;
use Modules\HR\Services\AttendanceCalculator;
use Modules\HR\Services\AttendanceCsv;
use Modules\HR\Services\PunchGrouper;
use Modules\HR\Services\ScheduleLookup;

/**
 * Imports a biometric attendance CSV for a property (Step 6.2), all or nothing: one unknown employee number or bad line
 * rejects the file and says which. Punch lists are grouped into days (`PunchGrouper`, night shifts included); each day is
 * measured against the roster and holidays (`AttendanceCalculator`). Importing a file again replaces the imported days;
 * days entered by hand are never touched. Days before an employee joined or after they left are skipped.
 */
class ImportAttendance extends Action
{
    public function __construct(
        private readonly AttendanceCsv $csv,
        private readonly PunchGrouper $grouper,
        private readonly AttendanceCalculator $calculator,
        private readonly ScheduleLookup $schedule,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return array{import: AttendanceImport, days: int, skipped: int, rows: int}
     *
     * @throws HrRuleViolated
     */
    public function handle(int $propertyId, string $filename, string $content, ?int $userId): array
    {
        $parsed = $this->csv->parse($content);

        if ($parsed['errors'] !== []) {
            throw new HrRuleViolated($this->problems($parsed['errors']));
        }

        $lines = [...$parsed['punches'], ...$parsed['days']];
        $employees = Employee::query()->where('property_id', $propertyId)->whereIn('employee_no', array_values(array_unique(array_column($lines, 'employee'))))->get()
            ->keyBy(fn (Employee $employee): string => strtoupper($employee->employee_no));
        $errors = [];

        foreach ($lines as $line) {
            if (! isset($employees[strtoupper($line['employee'])])) {
                $errors[strtoupper($line['employee'])] ??= __('Line :n: there is no employee ":no" at this property.', ['n' => $line['line'], 'no' => $line['employee']]);
            }
        }

        if ($errors !== []) {
            throw new HrRuleViolated($this->problems(array_values($errors)));
        }

        $dates = array_map(fn (array $line): string => substr($line['at'] ?? $line['date'], 0, 10), $lines);
        $first = CarbonImmutable::parse(min($dates))->subDay()->toDateString();
        $last = max($dates);
        $ids = $employees->pluck('id')->all();
        $rosters = $this->schedule->rosters($ids, $first, $last);
        $holidays = $this->schedule->holidays($propertyId, $first, $last);
        $days = $parsed['shape'] === 'daily' ? $this->dailyDays($parsed['days'], $employees) : $this->punchDays($parsed['punches'], $employees, $rosters);

        $manual = Attendance::query()->whereIn('employee_id', $ids)->whereBetween('work_date', [$first, $last])->where('source', AttendanceSource::Manual->value)->get()
            ->mapWithKeys(fn (Attendance $attendance): array => [$attendance->employee_id.'|'.$attendance->work_date->toDateString() => true]);
        $tenantId = $this->tenant->tenantOrFail(self::class)->id;
        $now = now();
        $rows = [];
        $skipped = 0;

        foreach ($days as $employeeId => $byDate) {
            $employee = $employees->firstWhere('id', $employeeId);

            foreach ($byDate as $date => $times) {
                if (! $employee instanceof Employee || $date < $employee->joined_on->toDateString() || ($employee->exited_on !== null && $date > $employee->exited_on->toDateString()) || isset($manual[$employeeId.'|'.$date])) {
                    $skipped++;

                    continue;
                }

                $shift = $rosters[$employeeId][$date] ?? null;
                $result = $this->calculator->calculate($shift?->times(), $times['in'], $times['out'], isset($holidays[$date]));
                $rows[] = [
                    'tenant_id' => $tenantId, 'property_id' => $propertyId, 'employee_id' => $employeeId, 'work_date' => $date, 'shift_id' => $shift?->id,
                    'clock_in' => $times['in'] !== null ? $times['in'].':00' : null, 'clock_out' => $times['out'] !== null ? $times['out'].':00' : null, 'status' => $result->status->value,
                    'late_minutes' => $result->lateMinutes, 'early_leave_minutes' => $result->earlyLeaveMinutes, 'overtime_minutes' => $result->overtimeMinutes, 'worked_minutes' => $result->workedMinutes,
                    'source' => AttendanceSource::Import->value, 'notes' => null, 'recorded_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }

        return $this->transaction(function () use ($propertyId, $filename, $parsed, $rows, $skipped, $userId): array {
            $import = AttendanceImport::query()->create(['property_id' => $propertyId, 'filename' => mb_substr($filename, 0, 200), 'rows_read' => $parsed['rows'], 'days_imported' => count($rows), 'days_skipped' => $skipped, 'imported_by' => $userId]);

            foreach (array_chunk($rows, 400) as $chunk) {
                Attendance::query()->upsert(array_map(fn (array $row): array => $row + ['attendance_import_id' => $import->id], $chunk), ['employee_id', 'work_date'],
                    ['property_id', 'shift_id', 'clock_in', 'clock_out', 'status', 'late_minutes', 'early_leave_minutes', 'overtime_minutes', 'worked_minutes', 'source', 'attendance_import_id', 'notes', 'recorded_by', 'updated_at']);
            }

            return ['import' => $import, 'days' => count($rows), 'skipped' => $skipped, 'rows' => $parsed['rows']];
        }, attempts: 3);
    }

    /**
     * @param  list<array{line: int, employee: string, date: string, in: string|null, out: string|null}>  $lines
     * @param  Collection<string, Employee>  $employees
     * @return array<int, array<string, array{in: string|null, out: string|null}>>
     */
    private function dailyDays(array $lines, $employees): array
    {
        $days = [];

        foreach ($lines as $line) {
            $days[$employees[strtoupper($line['employee'])]->id][$line['date']] = ['in' => $line['in'], 'out' => $line['out']];
        }

        return $days;
    }

    /**
     * @param  list<array{line: int, employee: string, at: string}>  $punches
     * @param  Collection<string, Employee>  $employees
     * @param  array<int, array<string, Shift|null>>  $rosters
     * @return array<int, array<string, array{in: string|null, out: string|null}>>
     */
    private function punchDays(array $punches, $employees, array $rosters): array
    {
        $byEmployee = [];

        foreach ($punches as $punch) {
            $byEmployee[$employees[strtoupper($punch['employee'])]->id][] = $punch['at'];
        }

        $days = [];

        foreach ($byEmployee as $employeeId => $times) {
            $days[$employeeId] = $this->grouper->group($times, function (string $date) use ($rosters, $employeeId): ?int {
                $shift = $rosters[$employeeId][$date] ?? null;

                return $shift instanceof Shift && $shift->crossesMidnight() ? $shift->times()->endMinutes() : null;
            });
        }

        return $days;
    }

    /**
     * @param  list<string>  $errors
     */
    private function problems(array $errors): string
    {
        $shown = array_slice($errors, 0, 5);
        $more = count($errors) - count($shown);

        return __('Nothing was imported.').' '.implode(' ', $shown).($more > 0 ? ' '.trans_choice('…and :count more problem.|…and :count more problems.', $more) : '');
    }
}
