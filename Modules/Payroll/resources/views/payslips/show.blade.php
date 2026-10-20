@php
    $money = fn (mixed $value): string => number_format((float) $value, 2);
    $kinds = ['earning' => __('Earning'), 'deduction' => __('Deduction')];
@endphp

<x-layouts::app :title="$payslip->employee_name" :subtitle="$run->period->format('F Y').' · '.$payslip->employee_no" :breadcrumbs="[__('Payroll') => null, __('Payroll runs') => route('payroll.runs.index'), $run->run_no => route('payroll.runs.show', $run), $payslip->employee_name => null]">
    <x-slot:actions><a href="{{ route('payroll.payslips.pdf', $payslip) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" data-pdf><i class="bi bi-file-earmark-pdf"></i> {{ __('PDF') }}</a></x-slot:actions>
    <div class="row">
        <div class="col-xl-7">
            <x-card :title="__('Payslip')" icon="bi-receipt">
                <p class="small text-body-secondary mb-2">{{ $payslip->designation }}@if ($payslip->department_name) · {{ $payslip->department_name }}@endif · {{ $payslip->structure_name }} · {{ __('basic :basic', ['basic' => $money($payslip->basic)]) }}
                    · {{ __(':employed of :days days', ['employed' => $payslip->days_employed, 'days' => $payslip->days_in_month]) }}</p>
                <table class="table table-sm align-middle mb-3" data-payslip-lines>
                    <tbody>
                        @foreach ($payslip->lines->sortBy('sort_order') as $line)
                            <tr data-line="{{ $line->code }}">
                                <td><span class="font-monospace">{{ $line->code }}</span> <span class="small text-body-secondary">{{ $line->name }}</span>@if ($line->note) <span class="small text-body-secondary">· {{ $line->note }}</span>@endif
                                    @if ($line->kind === \Modules\Payroll\Enums\ComponentKind::Employer)<span class="badge text-bg-info">{{ __('employer') }}</span>@endif
                                    @if ($line->source === \Modules\Payroll\Enums\LineSource::Adjustment)<span class="badge text-bg-warning">{{ __('adjustment') }}</span>@endif</td>
                                <td class="text-end font-monospace @if ($line->kind === \Modules\Payroll\Enums\ComponentKind::Deduction) text-danger @endif">{{ $money($line->amount) }}</td>
                                <td class="text-end">@if ($canAdjust && $line->source === \Modules\Payroll\Enums\LineSource::Adjustment)<x-confirm-delete :action="route('payroll.payslips.adjustments.destroy', [$payslip, $line])" :text="__('The adjustment will be removed.')" icon-only />@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold"><td>{{ __('Gross') }}</td><td class="text-end font-monospace" data-gross>{{ $money($payslip->gross) }}</td><td></td></tr>
                        <tr><td>{{ __('Deductions') }}</td><td class="text-end font-monospace" data-deductions>{{ $money($payslip->deductions) }}</td><td></td></tr>
                        <tr><td>{{ __('Income tax') }}</td><td class="text-end font-monospace" data-tax>{{ $money($payslip->tax) }}</td><td></td></tr>
                        <tr class="fw-bold table-light"><td>{{ __('Net pay') }}</td><td class="text-end font-monospace" data-net>{{ $money($payslip->net) }}</td><td></td></tr>
                    </tfoot>
                </table>
            </x-card>
        </div>
        <div class="col-xl-5">
            <x-card :title="__('This month')" icon="bi-calendar-week">
                <dl class="row mb-0">
                    <dt class="col-6">{{ __('Days absent') }}</dt><dd class="col-6">{{ rtrim(rtrim((string) $payslip->absent_days, '0'), '.') ?: '0' }}</dd>
                    <dt class="col-6">{{ __('Unpaid leave days') }}</dt><dd class="col-6">{{ rtrim(rtrim((string) $payslip->unpaid_leave_days, '0'), '.') ?: '0' }}</dd>
                    <dt class="col-6">{{ __('Overtime') }}</dt><dd class="col-6">{{ intdiv($payslip->overtime_minutes, 60) }}:{{ str_pad((string) ($payslip->overtime_minutes % 60), 2, '0', STR_PAD_LEFT) }}</dd>
                    <dt class="col-6">{{ __('Employer cost on top') }}</dt><dd class="col-6">{{ $money($payslip->employer_cost) }}</dd>
                    @if ($payslip->emailed_at)<dt class="col-6">{{ __('Emailed') }}</dt><dd class="col-6">{{ $payslip->emailed_at->inPropertyTime()->format('d M Y H:i') }}</dd>@endif
                </dl>
            </x-card>
            @if ($canAdjust)
                <x-card :title="__('Adjust this payslip')" icon="bi-pencil-square">
                    <form method="POST" action="{{ route('payroll.payslips.adjust', $payslip) }}" data-adjust-form>
                        @csrf
                        <x-form.select name="kind" :label="__('Kind')" :options="$kinds" value="earning" required :search="false" />
                        <x-form.input name="name" :label="__('Name')" required :help="__('e.g. Performance bonus, Uniform fine')" />
                        <x-form.input name="amount" type="number" step="0.01" min="0" :label="__('Amount')" required />
                        <x-form.input name="note" :label="__('Reason')" required />
                        <button type="submit" class="btn btn-primary">{{ __('Add') }}</button>
                        <p class="small text-body-secondary mt-2 mb-0">{{ __('The net pay follows; the withheld income tax does not change.') }}</p>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts::app>
