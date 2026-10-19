<x-layouts::app :title="__('My leave')" :subtitle="$employee?->full_name" :breadcrumbs="[__('HR') => null, __('My leave') => null]">
    @if (! $employee)
        <x-empty-state icon="bi-person-x" :title="__('Your user account is not linked to an employee record')" :message="__('Ask HR to link it, then your balances and requests appear here.')" />
    @else
        <div class="row">
            <div class="col-xl-7">
                <div class="row g-2 mb-3" data-my-balances>
                    @forelse ($balances as $balance)
                        <div class="col-6 col-md-4"><div class="border rounded p-2 h-100"><div class="small text-body-secondary">{{ $balance->leaveType->name }} · {{ $year }}</div><div class="fs-4 fw-semibold font-monospace" data-available="{{ $balance->leaveType->code }}">{{ $balance->available() }}</div><div class="small text-body-secondary">{{ __(':used used · :pending waiting', ['used' => $balance->used, 'pending' => $balance->pending]) }}</div></div></div>
                    @empty
                        <div class="col-12"><p class="text-body-secondary">{{ __('No leave has been granted to you for :year yet.', ['year' => $year]) }}</p></div>
                    @endforelse
                </div>
                <x-card :title="__('My requests')" icon="bi-calendar-check" body-class="p-0">
                    <table class="table table-sm align-middle mb-0" data-my-requests>
                        <thead><tr><th class="ps-3">{{ __('Request') }}</th><th>{{ __('Type') }}</th><th>{{ __('Dates') }}</th><th class="text-end">{{ __('Days') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($requests as $leave)
                                <tr><td class="ps-3"><a href="{{ route('hr.leave.show', $leave) }}">{{ $leave->request_no }}</a></td><td>{{ $leave->leaveType->name }}</td><td>{{ $leave->from_date->format('d M') }}@if (! $leave->from_date->eq($leave->to_date)) – {{ $leave->to_date->format('d M Y') }}@else {{ $leave->from_date->format('Y') }}@endif</td><td class="text-end">{{ $leave->days }}</td><td><x-status-badge :status="$leave->status" /></td></tr>
                            @empty
                                <tr><td colspan="5"><x-empty-state :title="__('No requests yet')" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-card>
            </div>
            <div class="col-xl-5">
                <x-card :title="__('Ask for leave')" icon="bi-calendar2-plus">@include('hr::leave._form', ['manage' => false, 'own' => $employee, 'chosen' => null])</x-card>
            </div>
        </div>
    @endif
</x-layouts::app>
