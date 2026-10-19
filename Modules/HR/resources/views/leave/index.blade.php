<x-layouts::app :title="__('Leave requests')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('HR') => null, __('Leave') => null]">
    @if ($canRequest)
        <x-slot:actions><a href="{{ route('hr.leave.create') }}" class="btn btn-sm btn-primary" data-new-leave><i class="bi bi-plus-lg"></i> {{ __('Ask for leave') }}</a></x-slot:actions>
    @endif
    <ul class="nav nav-pills mb-3" data-leave-filters>
        <li class="nav-item"><a @class(['nav-link', 'active' => $status === null]) href="{{ route('hr.leave.index') }}">{{ __('All') }}</a></li>
        @foreach ($statuses as $value => $label)
            <li class="nav-item"><a @class(['nav-link', 'active' => $status === $value]) href="{{ route('hr.leave.index', ['status' => $value]) }}">{{ $label }}</a></li>
        @endforeach
    </ul>
    <x-card body-class="p-0">
        <table class="table table-sm table-hover align-middle mb-0" data-leave-requests>
            <thead><tr><th class="ps-3">{{ __('Request') }}</th><th>{{ __('Employee') }}</th><th>{{ __('Type') }}</th><th>{{ __('Dates') }}</th><th class="text-end">{{ __('Days') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
                @forelse ($requests as $leave)
                    <tr data-leave="{{ $leave->request_no }}">
                        <td class="ps-3"><a href="{{ route('hr.leave.show', $leave) }}">{{ $leave->request_no }}</a></td>
                        <td><a href="{{ route('hr.employees.show', $leave->employee) }}">{{ $leave->employee->full_name }}</a></td>
                        <td><span class="badge text-bg-{{ $leave->leaveType->color }}">{{ $leave->leaveType->code }}</span> {{ $leave->leaveType->name }}</td>
                        <td>{{ $leave->from_date->format('d M Y') }}@if (! $leave->from_date->eq($leave->to_date)) – {{ $leave->to_date->format('d M Y') }}@endif</td>
                        <td class="text-end">{{ $leave->days }}</td>
                        <td><x-status-badge :status="$leave->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state :title="__('No leave requests')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts::app>
