@php
    $previous = \Carbon\CarbonImmutable::parse($date)->subDay()->toDateString();
    $next = \Carbon\CarbonImmutable::parse($date)->addDay()->toDateString();
    $minutes = fn (int $value): string => $value > 0 ? $value : '—';
@endphp

<x-layouts::app :title="__('Attendance')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('HR') => null, __('Attendance') => null]">
    <x-slot:actions>
        @can('import', \Modules\HR\Models\Attendance::class)<a href="{{ route('hr.attendance.import') }}" class="btn btn-sm btn-outline-secondary" data-import-link><i class="bi bi-upload"></i> {{ __('Import') }}</a>@endcan
        <a href="{{ route('hr.attendance.report') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-bar-chart"></i> {{ __('Monthly report') }}</a>
    </x-slot:actions>

    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div class="btn-group">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.attendance.index', array_filter(['date' => $previous, 'department' => $department])) }}"><i class="bi bi-chevron-left"></i></a>
            <span class="btn btn-sm btn-outline-secondary disabled" data-day>{{ \Carbon\CarbonImmutable::parse($date)->format('D, d M Y') }}</span>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.attendance.index', array_filter(['date' => $next, 'department' => $department])) }}"><i class="bi bi-chevron-right"></i></a>
        </div>
        <form method="GET" action="{{ route('hr.attendance.index') }}" class="d-flex gap-2">
            <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm" aria-label="{{ __('Date') }}">
            <select name="department" class="form-select form-select-sm" aria-label="{{ __('Department') }}">
                <option value="">{{ __('All departments') }}</option>
                @foreach ($departments as $id => $name)<option value="{{ $id }}" @selected($department === $id)>{{ $name }}</option>@endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-primary">{{ __('Show') }}</button>
        </form>
    </div>

    @if ($holiday)
        <div class="alert alert-primary py-2" data-holiday-banner><i class="bi bi-balloon"></i> {{ $holiday->name }}: {{ __('all time worked today counts as overtime.') }}</div>
    @endif

    <div x-data="{ employee: '', name: '', clockIn: '', clockOut: '' }">
        <x-card body-class="p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" data-attendance>
                    <thead><tr><th class="ps-3">{{ __('Employee') }}</th><th>{{ __('Shift') }}</th><th>{{ __('In') }}</th><th>{{ __('Out') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Late') }}</th><th class="text-end">{{ __('Early') }}</th><th class="text-end">{{ __('Overtime') }}</th><th class="text-end">{{ __('Worked') }}</th>@if ($canRecord)<th></th>@endif</tr></thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            @php($record = $records[$employee->id] ?? null)
                            @php($shift = array_key_exists($date, $rosters[$employee->id] ?? []) ? $rosters[$employee->id][$date] : false)
                            <tr data-attendance-row="{{ $employee->employee_no }}">
                                <td class="ps-3"><a href="{{ route('hr.employees.show', $employee) }}">{{ $employee->full_name }}</a> <span class="small text-body-secondary">{{ $employee->employee_no }}</span></td>
                                <td>@if ($shift)<span class="badge text-bg-{{ $shift->color }}">{{ $shift->code }}</span> <span class="small text-body-secondary">{{ $shift->window() }}</span>@elseif ($shift === null)<span class="small text-body-secondary">{{ __('Day off') }}</span>@else<span class="text-body-tertiary">—</span>@endif</td>
                                <td class="font-monospace">{{ $record?->in() ?: '—' }}</td>
                                <td class="font-monospace">{{ $record?->out() ?: '—' }}</td>
                                <td>@if ($record)<x-status-badge :status="$record->status" />@if ($record->source === \Modules\HR\Enums\AttendanceSource::Manual) <i class="bi bi-pencil-square text-body-secondary" title="{{ __('Entered by hand') }}"></i>@endif @elseif ($shift && $date <= $today ?? true)<span class="text-body-secondary small">{{ __('No record') }}</span>@else — @endif</td>
                                <td class="text-end">{{ $minutes($record?->late_minutes ?? 0) }}</td>
                                <td class="text-end">{{ $minutes($record?->early_leave_minutes ?? 0) }}</td>
                                <td class="text-end">{{ $minutes($record?->overtime_minutes ?? 0) }}</td>
                                <td class="text-end font-monospace">{{ $record ? intdiv($record->worked_minutes, 60).':'.str_pad((string) ($record->worked_minutes % 60), 2, '0', STR_PAD_LEFT) : '—' }}</td>
                                @if ($canRecord)
                                    <td class="text-end pe-3 text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#record-attendance" data-record="{{ $employee->employee_no }}"
                                                @click="employee = {{ $employee->id }}; name = @js($employee->full_name); clockIn = @js($record?->in()); clockOut = @js($record?->out())"><i class="bi bi-pencil"></i> {{ $record ? __('Correct') : __('Record') }}</button>
                                        @if ($record)<x-confirm-delete :action="route('hr.attendance.destroy', $record)" :text="__('The record for :name will be removed.', ['name' => $employee->full_name])" icon-only />@endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="10"><x-empty-state :title="__('No employees')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        @if ($canRecord)
            <x-modal id="record-attendance" :title="__('Record attendance')">
                <form method="POST" action="{{ route('hr.attendance.store') }}" id="form-record-attendance">
                    @csrf
                    <input type="hidden" name="employee_id" x-model="employee">
                    <input type="hidden" name="work_date" value="{{ $date }}">
                    <p class="fw-semibold" x-text="name"></p>
                    <div class="row">
                        <div class="col-6"><x-form.input name="clock_in" type="time" :label="__('In')" x-model="clockIn" /></div>
                        <div class="col-6"><x-form.input name="clock_out" type="time" :label="__('Out')" x-model="clockOut" /></div>
                    </div>
                    <x-form.select name="status" :label="__('Mark the day as')" :options="$overrides" :placeholder="__('Work it out from the punches')" :search="false" />
                    <x-form.input name="notes" :label="__('Notes')" />
                </form>
                <x-slot:footer><button type="submit" form="form-record-attendance" class="btn btn-primary">{{ __('Save') }}</button></x-slot:footer>
            </x-modal>
        @endif
    </div>
</x-layouts::app>
