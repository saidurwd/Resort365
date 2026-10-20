@php
    $money = fn (mixed $value): string => number_format((float) $value, 2);
    $open = $run->isOpen();
@endphp

<x-layouts::app :title="$run->period->format('F Y').' · '.$run->run_no" :subtitle="$subtitle" :breadcrumbs="[__('Payroll') => null, __('Payroll runs') => route('payroll.runs.index'), $run->run_no => null]">
    <x-slot:actions>
        @if ($can['export'])
            <a href="{{ route('payroll.runs.register', $run) }}" class="btn btn-sm btn-outline-secondary" data-register><i class="bi bi-download"></i> {{ __('Register') }}</a>
            @if (in_array($run->status, [\Modules\Payroll\Enums\RunStatus::Approved, \Modules\Payroll\Enums\RunStatus::Paid], true))<a href="{{ route('payroll.runs.bank-file', $run) }}" class="btn btn-sm btn-outline-secondary" data-bank-file><i class="bi bi-bank"></i> {{ __('Bank file') }}</a>@endif
        @endif
        @if ($can['email'] && in_array($run->status, [\Modules\Payroll\Enums\RunStatus::Approved, \Modules\Payroll\Enums\RunStatus::Paid], true))
            <form method="POST" action="{{ route('payroll.runs.email', $run) }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-secondary" data-email><i class="bi bi-envelope"></i> {{ __('Email payslips') }}</button></form>
        @endif
        @if ($can['create'] && $open)
            <form method="POST" action="{{ route('payroll.runs.calculate', $run) }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-primary" data-calculate><i class="bi bi-calculator"></i> {{ $run->status === \Modules\Payroll\Enums\RunStatus::Draft ? __('Calculate') : __('Calculate again') }}</button></form>
            @if ($run->status === \Modules\Payroll\Enums\RunStatus::Calculated)
                <form method="POST" action="{{ route('payroll.runs.submit', $run) }}" class="d-inline" data-confirm="{{ __('Send this run for approval?') }}" data-confirm-text="{{ __('From here the run and its payslips are locked.') }}" data-confirm-button="{{ __('Send for approval') }}" data-confirm-cancel="{{ __('Cancel') }}">@csrf<button type="submit" class="btn btn-sm btn-success" data-submit><i class="bi bi-send-check"></i> {{ __('Send for approval') }}</button></form>
            @endif
            <x-confirm-delete :action="route('payroll.runs.destroy', $run)" :text="__('The run and its payslips will be deleted.')" />
        @endif
    </x-slot:actions>

    <div class="row g-2 mb-3" data-run-summary>
        <div class="col-6 col-md-3 col-xl-2"><div class="border rounded p-2 h-100"><div class="small text-body-secondary">{{ __('Status') }}</div><div class="fs-6"><x-status-badge :status="$run->status" /></div></div></div>
        @foreach ([__('Employees') => (string) $run->employees_count, __('Gross') => $money($run->gross_total), __('Deductions') => $money($run->deductions_total), __('Income tax') => $money($run->tax_total), __('Net pay') => $money($run->net_total), __('Employer cost') => $money($run->employer_total)] as $label => $value)
            <div class="col-6 col-md-3 col-xl-2"><div class="border rounded p-2 h-100"><div class="small text-body-secondary">{{ $label }}</div><div class="fs-5 fw-semibold font-monospace" data-figure="{{ $label }}">{{ $value }}</div></div></div>
        @endforeach
    </div>

    @if ($approval)
        <div class="alert alert-info py-2" data-approval><i class="bi bi-person-check"></i> {{ __('Approval') }}: <a href="{{ $approval->url }}">{{ $approval->status->label() }}@if ($approval->currentStep) · {{ $approval->currentStep }}@endif</a></div>
    @endif
    @if ($run->status === \Modules\Payroll\Enums\RunStatus::Paid)
        <div class="alert alert-success py-2" data-paid><i class="bi bi-check-circle"></i> {{ __('Paid on :date by :method', ['date' => $run->paid_on?->format('d M Y'), 'method' => mb_strtolower($run->payment_method?->label() ?? '')]) }}@if ($run->payment_reference) · {{ $run->payment_reference }}@endif</div>
    @endif
    @if (! $open)<p class="small text-body-secondary" data-locked><i class="bi bi-lock"></i> {{ __('This run is locked: its payslips cannot be changed.') }}</p>@endif
    @foreach ($run->warnings ?? [] as $warning)
        <div class="alert alert-warning py-2" data-warning>{{ $warning }}</div>
    @endforeach

    <x-card :title="__('Payslips')" icon="bi-receipt" body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-payslips>
                <thead><tr><th class="ps-3">{{ __('Employee') }}</th><th>{{ __('Department') }}</th><th class="text-end">{{ __('Days') }}</th><th class="text-end">{{ __('Overtime') }}</th><th class="text-end">{{ __('Absent') }}</th><th class="text-end">{{ __('Gross') }}</th><th class="text-end">{{ __('Deductions') }}</th><th class="text-end">{{ __('Tax') }}</th><th class="text-end pe-3">{{ __('Net pay') }}</th></tr></thead>
                <tbody>
                    @forelse ($run->payslips as $slip)
                        <tr data-payslip="{{ $slip->employee_no }}" @class(['table-danger' => (float) $slip->net < 0])>
                            <td class="ps-3">@if ($can['payslips'])<a href="{{ route('payroll.payslips.show', $slip) }}">{{ $slip->employee_name }}</a>@else{{ $slip->employee_name }}@endif <span class="small text-body-secondary">{{ $slip->employee_no }}</span></td>
                            <td>{{ $slip->department_name ?? '—' }}</td>
                            <td class="text-end">{{ $slip->days_employed }}/{{ $slip->days_in_month }}</td>
                            <td class="text-end">{{ $slip->overtime_minutes > 0 ? intdiv($slip->overtime_minutes, 60).':'.str_pad((string) ($slip->overtime_minutes % 60), 2, '0', STR_PAD_LEFT) : '—' }}</td>
                            <td class="text-end">{{ (float) $slip->absent_days + (float) $slip->unpaid_leave_days > 0 ? rtrim(rtrim((string) ((float) $slip->absent_days + (float) $slip->unpaid_leave_days), '0'), '.') : '—' }}</td>
                            <td class="text-end font-monospace">{{ $money($slip->gross) }}</td>
                            <td class="text-end font-monospace">{{ $money($slip->deductions) }}</td>
                            <td class="text-end font-monospace">{{ $money($slip->tax) }}</td>
                            <td class="text-end font-monospace pe-3 fw-semibold">{{ $money($slip->net) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty-state :title="$run->status === \Modules\Payroll\Enums\RunStatus::Draft ? __('Not calculated yet') : __('No payslips')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    @if ($can['pay'] && $run->status === \Modules\Payroll\Enums\RunStatus::Approved)
        <x-card :title="__('Record the payment')" icon="bi-cash-coin">
            <form method="POST" action="{{ route('payroll.runs.pay', $run) }}" class="row g-2 align-items-end" data-pay-form>
                @csrf
                <div class="col-md-3"><x-form.select name="payment_method" :label="__('Paid by')" :options="$methods" value="bank_transfer" required :search="false" wrapper-class="mb-0" /></div>
                <div class="col-md-3"><x-form.date name="paid_on" :label="__('Paid on')" :value="$today" required wrapper-class="mb-0" /></div>
                <div class="col-md-4"><x-form.input name="payment_reference" :label="__('Reference')" wrapper-class="mb-0" /></div>
                <div class="col-md-2"><button type="submit" class="btn btn-success w-100"><i class="bi bi-check2-circle"></i> {{ __('Mark paid') }}</button></div>
            </form>
        </x-card>
    @endif
    <x-audit-trail :entries="$trail" />
</x-layouts::app>
