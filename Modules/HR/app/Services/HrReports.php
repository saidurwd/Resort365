<?php

namespace Modules\HR\Services;

use Modules\HR\Enums\EmploymentType;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveBalance;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * HR reports (Step 6.6): headcount by department and employment type with joiners and leavers, and leave balances by
 * department and leave type. (The attendance summary is `AttendanceReports::byDepartment`.) Each answers the array the report
 * table and CSV download use.
 */
class HrReports
{
    public function __construct(private readonly DepartmentDirectory $departments) {}

    /**
     * Who works at the property on the last day, by department and employment type, and who joined or left from the first
     * day to the last.
     *
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function headcount(int $propertyId, string $from, string $to): array
    {
        $names = collect($this->departments->all(false))->pluck('name', 'id');
        $types = EmploymentType::cases();
        $employees = Employee::query()->where('property_id', $propertyId)->whereDate('joined_on', '<=', $to)->where(fn ($query) => $query->whereNull('exited_on')->orWhereDate('exited_on', '>=', $from))->get();
        $groups = [];
        $sum = ['total' => 0, 'joined' => 0, 'left' => 0, ...array_fill_keys(array_map(fn (EmploymentType $type): string => $type->value, $types), 0)];

        foreach ($employees as $employee) {
            $key = $names[$employee->department_id] ?? '—';
            $groups[$key] ??= ['total' => 0, 'joined' => 0, 'left' => 0, ...array_fill_keys(array_map(fn (EmploymentType $type): string => $type->value, $types), 0)];
            $working = $employee->exited_on === null || $employee->exited_on->toDateString() > $to;

            if ($working) {
                $groups[$key]['total']++;
                $groups[$key][$employee->employment_type->value]++;
                $sum['total']++;
                $sum[$employee->employment_type->value]++;
            }

            if ($employee->joined_on->toDateString() >= $from) {
                $groups[$key]['joined']++;
                $sum['joined']++;
            }

            if ($employee->exited_on !== null && $employee->exited_on->toDateString() >= $from && $employee->exited_on->toDateString() <= $to) {
                $groups[$key]['left']++;
                $sum['left']++;
            }
        }

        ksort($groups);
        $line = fn (string $label, array $g): array => [$label, (string) $g['total'], ...array_map(fn (EmploymentType $type): string => (string) $g[$type->value], $types), (string) $g['joined'], (string) $g['left']];

        return [
            'title' => __('Headcount on :date', ['date' => $to]),
            'columns' => [__('Department'), __('Employees'), ...array_map(fn (EmploymentType $type): string => $type->label(), $types), __('Joined'), __('Left')],
            'rows' => array_map(fn (string $department, array $g): array => $line($department, $g), array_keys($groups), array_values($groups)),
            'totals' => $line(__('Total'), $sum),
            'summary' => [['label' => __('Employees'), 'value' => (string) $sum['total']], ['label' => __('Joined'), 'value' => (string) $sum['joined']], ['label' => __('Left'), 'value' => (string) $sum['left']]],
            'note' => __('Employees are the people employed on :date. Joined and left count the period from :from to :date.', ['date' => $to, 'from' => $from]),
        ];
    }

    /**
     * The year's leave balances added up by department and leave type.
     *
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function leaveBalances(int $propertyId, int $year): array
    {
        $names = collect($this->departments->all(false))->pluck('name', 'id');
        $groups = [];
        $keys = ['entitled', 'carried', 'adjusted', 'used', 'pending', 'available'];
        $sum = array_fill_keys($keys, 0.0);

        foreach (LeaveBalance::query()->with(['employee', 'leaveType'])->where('year', $year)->whereHas('employee', fn ($query) => $query->where('property_id', $propertyId))->get() as $balance) {
            $key = ($names[$balance->employee->department_id] ?? '—')."\0".$balance->leaveType->name;
            $groups[$key] ??= ['people' => 0, ...array_fill_keys($keys, 0.0)];
            $available = (float) $balance->entitled + (float) $balance->carried_forward + (float) $balance->adjusted - (float) $balance->used - (float) $balance->pending;
            $figures = ['entitled' => (float) $balance->entitled, 'carried' => (float) $balance->carried_forward, 'adjusted' => (float) $balance->adjusted, 'used' => (float) $balance->used, 'pending' => (float) $balance->pending, 'available' => $available];
            $groups[$key]['people']++;

            foreach ($figures as $name => $value) {
                $groups[$key][$name] += $value;
                $sum[$name] += $value;
            }
        }

        ksort($groups);
        $days = fn (float $value): string => number_format($value, 1);
        $rows = [];

        foreach ($groups as $key => $g) {
            [$department, $type] = explode("\0", $key);
            $rows[] = [$department, $type, (string) $g['people'], $days($g['entitled']), $days($g['carried']), $days($g['adjusted']), $days($g['used']), $days($g['pending']), $days($g['available'])];
        }

        return [
            'title' => __('Leave balances for :year', ['year' => $year]),
            'columns' => [__('Department'), __('Leave type'), __('Employees'), __('Entitled'), __('Carried forward'), __('Adjusted'), __('Used'), __('Pending'), __('Available')],
            'rows' => $rows,
            'totals' => [__('Total'), '', '', $days($sum['entitled']), $days($sum['carried']), $days($sum['adjusted']), $days($sum['used']), $days($sum['pending']), $days($sum['available'])],
            'summary' => [['label' => __('Days used'), 'value' => $days($sum['used'])], ['label' => __('Days pending'), 'value' => $days($sum['pending'])], ['label' => __('Days available'), 'value' => $days($sum['available'])]],
            'note' => __('Available = entitled + carried forward + adjusted − used − pending, in days, for the leave types that keep a balance.'),
        ];
    }
}
