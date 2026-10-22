@php
    $previous = $month->subMonth()->format('Y-m');
    $next = $month->addMonth()->format('Y-m');
@endphp

<x-layouts::app :title="__('Leave calendar')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('HR') => null, __('Leave calendar') => null]">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div class="btn-group">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.leave.calendar', array_filter(['month' => $previous, 'department' => $department])) }}"><i class="bi bi-chevron-left"></i></a>
            <span class="btn btn-sm btn-outline-secondary disabled" data-month>{{ $month->format('F Y') }}</span>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.leave.calendar', array_filter(['month' => $next, 'department' => $department])) }}"><i class="bi bi-chevron-right"></i></a>
        </div>
        <form method="GET" action="{{ route('hr.leave.calendar') }}" class="d-flex gap-2">
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            <select name="department" class="form-select form-select-sm" aria-label="{{ __('Department') }}" data-submit-on-change>
                <option value="">{{ __('All departments') }}</option>
                @foreach ($departments as $id => $name)<option value="{{ $id }}" @selected($department === $id)>{{ $name }}</option>@endforeach
            </select>
        </form>
    </div>
    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle text-center mb-0" data-leave-calendar>
                <thead>
                    <tr>
                        <th class="text-start">{{ __('Employee') }}</th>
                        @foreach ($dates as $date)
                            @php($day = \Carbon\CarbonImmutable::parse($date))
                            <th @class(['small', 'table-primary' => isset($holidays[$date])]) title="{{ $holidays[$date]->name ?? '' }}">{{ $day->format('D') }}<br>{{ $day->format('d') }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr data-calendar-row="{{ $employee->employee_no }}">
                            <td class="text-start text-nowrap"><a href="{{ route('hr.employees.show', $employee) }}">{{ $employee->full_name }}</a></td>
                            @foreach ($dates as $date)
                                @php($leave = $cells[$employee->id][$date] ?? null)
                                <td @class(['p-1', 'table-primary' => isset($holidays[$date])]) data-cell="{{ $date }}">
                                    @if ($leave)<a href="{{ route('hr.leave.show', $leave) }}" @class(['badge', 'text-bg-'.$leave->leaveType->color => $leave->status === \Modules\HR\Enums\LeaveStatus::Approved, 'border', 'text-body' => $leave->status !== \Modules\HR\Enums\LeaveStatus::Approved]) title="{{ $leave->leaveType->name }} · {{ $leave->status->label() }}">{{ $leave->leaveType->code }}</a>@endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($dates) + 1 }}"><x-empty-state :title="__('No employees')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
    <p class="small text-body-secondary mt-2">{{ __('Solid badges are approved leave; outlined ones are waiting for approval.') }}</p>
</x-layouts::app>
