<x-layouts::app :title="$leave->request_no" :subtitle="$leave->employee->full_name" :breadcrumbs="[__('HR') => null, __('Leave') => route('hr.leave.index'), $leave->request_no => null]">
    <x-slot:actions>
        @if ($canCancel)
            <form method="POST" action="{{ route('hr.leave.cancel', $leave) }}" data-confirm="{{ $leave->status === \Modules\HR\Enums\LeaveStatus::Pending ? __('Withdraw this request?') : __('Cancel this leave?') }}" data-confirm-text="{{ __('The days go back on the balance and the roster gets its shifts back.') }}" data-confirm-button="{{ __('Yes, cancel it') }}" data-confirm-cancel="{{ __('Keep it') }}" data-confirm-variant="danger">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger" data-cancel-leave><i class="bi bi-x-circle"></i> {{ $leave->status === \Modules\HR\Enums\LeaveStatus::Pending ? __('Withdraw') : __('Cancel leave') }}</button>
            </form>
        @endif
    </x-slot:actions>
    <div class="row">
        <div class="col-xl-7">
            <x-card :title="__('Leave')" icon="bi-calendar2-heart">
                <dl class="row mb-0" data-leave-summary>
                    <dt class="col-sm-4">{{ __('Employee') }}</dt><dd class="col-sm-8"><a href="{{ route('hr.employees.show', $leave->employee) }}">{{ $leave->employee->full_name }}</a> <span class="small text-body-secondary">{{ $leave->employee->employee_no }}</span></dd>
                    <dt class="col-sm-4">{{ __('Type') }}</dt><dd class="col-sm-8"><span class="badge text-bg-{{ $leave->leaveType->color }}">{{ $leave->leaveType->code }}</span> {{ $leave->leaveType->name }} · {{ $leave->leaveType->is_paid ? __('paid') : __('unpaid') }}</dd>
                    <dt class="col-sm-4">{{ __('Dates') }}</dt><dd class="col-sm-8">{{ $leave->from_date->format('D, d M Y') }}@if (! $leave->from_date->eq($leave->to_date)) – {{ $leave->to_date->format('D, d M Y') }}@endif @if ($leave->half_day)<span class="badge text-bg-info">{{ $leave->half_day->label() }}</span>@endif</dd>
                    <dt class="col-sm-4">{{ __('Days charged') }}</dt><dd class="col-sm-8 font-monospace" data-days>{{ $leave->days }}</dd>
                    <dt class="col-sm-4">{{ __('Status') }}</dt><dd class="col-sm-8"><x-status-badge :status="$leave->status" /></dd>
                    @if ($approval)<dt class="col-sm-4">{{ __('Approval') }}</dt><dd class="col-sm-8" data-approval><a href="{{ $approval->url }}">{{ $approval->status->label() }}@if ($approval->currentStep) · {{ $approval->currentStep }}@endif</a></dd>@endif
                    @if ($leave->reason)<dt class="col-sm-4">{{ __('Reason') }}</dt><dd class="col-sm-8">{!! nl2br(e($leave->reason)) !!}</dd>@endif
                    @if ($leave->leaveType->document_after_days && (float) $leave->days > $leave->leaveType->document_after_days)
                        <dt class="col-sm-4">{{ __('Paper') }}</dt><dd class="col-sm-8 text-warning">{{ __('A certificate or other paper is expected for more than :days days: add it below.', ['days' => $leave->leaveType->document_after_days]) }}</dd>
                    @endif
                </dl>
            </x-card>
            <x-attachments :subject="$leave" :title="__('Certificate and papers')" />
        </div>
        <div class="col-xl-5">
            <x-card :title="__('Balance')" icon="bi-wallet2" body-class="p-0">
                <table class="table table-sm mb-0" data-balances>
                    <thead><tr><th class="ps-3">{{ __('Year') }}</th><th class="text-end">{{ __('Entitled') }}</th><th class="text-end">{{ __('Used') }}</th><th class="text-end">{{ __('Waiting') }}</th><th class="text-end pe-3">{{ __('Available') }}</th></tr></thead>
                    <tbody>
                        @forelse ($balances as $balance)
                            <tr><td class="ps-3">{{ $balance->year }}</td><td class="text-end">{{ $balance->entitled }}</td><td class="text-end">{{ $balance->used }}</td><td class="text-end">{{ $balance->pending }}</td><td class="text-end pe-3 fw-semibold">{{ $balance->available() }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="ps-3 text-body-secondary">{{ $leave->leaveType->has_balance ? __('No balance.') : __('This leave has no balance.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
            <x-audit-trail :entries="$trail" />
        </div>
    </div>
</x-layouts::app>
