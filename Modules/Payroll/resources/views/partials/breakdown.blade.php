{{-- A month of pay: the lines, then gross, deductions, tax and net, and the yearly tax working. $breakdown is a SalaryBreakdown. --}}
@php
    $money = fn (string $value): string => number_format((float) $value, 2);
@endphp
<table class="table table-sm align-middle mb-3" data-breakdown>
    <tbody>
        @foreach ($breakdown->lines as $line)
            <tr data-breakdown-line="{{ $line->code }}">
                <td><span class="font-monospace">{{ $line->code }}</span> <span class="small text-body-secondary">{{ $line->name }}</span>
                    @if ($line->frequency === \Modules\Payroll\Enums\ComponentFrequency::Annual)<span class="badge text-bg-warning">{{ trans_choice(':count time a year|:count times a year', $line->annualTimes) }}</span>@endif
                    @if ($line->kind === \Modules\Payroll\Enums\ComponentKind::Employer)<span class="badge text-bg-info">{{ __('employer') }}</span>@endif
                </td>
                <td class="text-end font-monospace @if ($line->kind === \Modules\Payroll\Enums\ComponentKind::Deduction) text-danger @endif">{{ $money($line->amount) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="fw-semibold"><td>{{ __('Gross (monthly earnings)') }}</td><td class="text-end font-monospace" data-gross>{{ $money($breakdown->gross) }}</td></tr>
        <tr><td>{{ __('Deductions') }}</td><td class="text-end font-monospace" data-deductions>{{ $money($breakdown->deductions) }}</td></tr>
        <tr><td>{{ __('Income tax withheld') }}</td><td class="text-end font-monospace" data-tax>{{ $money($breakdown->tax) }}</td></tr>
        <tr class="fw-bold table-light"><td>{{ __('Net pay') }}</td><td class="text-end font-monospace" data-net>{{ $money($breakdown->net) }}</td></tr>
        <tr class="text-body-secondary"><td>{{ __('Employer cost on top (provident fund, gratuity)') }}</td><td class="text-end font-monospace">{{ $money($breakdown->employerCost) }}</td></tr>
    </tfoot>
</table>
@if ($breakdown->taxDetails)
    @php($tax = $breakdown->taxDetails)
    <div class="small text-body-secondary" data-tax-working>
        {{ __('Yearly salary income :income; exempt :exemption; taxable :taxable; tax-free amount :threshold; tax for the year :tax:minimum, withheld in twelve parts.', [
            'income' => $money($tax->income), 'exemption' => $money($tax->exemption), 'taxable' => $money($tax->taxableIncome), 'threshold' => $money($tax->threshold), 'tax' => $money($tax->tax),
            'minimum' => $tax->minimumApplied ? ' ('.__('the minimum tax').')' : '',
        ]) }}
    </div>
@endif
