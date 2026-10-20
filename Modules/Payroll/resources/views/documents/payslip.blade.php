<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Payslip') }} {{ $payslip->employee_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 4px 6px; text-align: left; }
        .r { text-align: right; }
        .head td { padding: 2px 6px; }
        .lines td { border-bottom: 1px solid #ddd; }
        .total td { border-top: 2px solid #222; font-weight: bold; }
        .muted { color: #777; }
    </style>
</head>
<body>
    <table class="head">
        <tr><td><h1>{{ $property->name ?? config('app.name') }}</h1><div class="muted">{{ __('Payslip for :month', ['month' => $run->period->format('F Y')]) }}</div></td><td class="r muted">{{ $run->run_no }}</td></tr>
    </table>
    <table class="head" style="margin-top: 8px">
        <tr><td>{{ __('Employee') }}: <b>{{ $payslip->employee_name }}</b> ({{ $payslip->employee_no }})</td><td>{{ __('Designation') }}: {{ $payslip->designation }}</td></tr>
        <tr><td>{{ __('Department') }}: {{ $payslip->department_name ?? '—' }}</td><td>{{ __('Days worked') }}: {{ $payslip->days_employed }} / {{ $payslip->days_in_month }}</td></tr>
    </table>
    <table class="lines" style="margin-top: 10px">
        <thead><tr><th>{{ __('Earnings') }}</th><th class="r">{{ __('Amount') }}</th></tr></thead>
        @foreach ($payslip->lines->where('kind', \Modules\Payroll\Enums\ComponentKind::Earning)->sortBy('sort_order') as $line)
            <tr><td>{{ $line->name }}@if ($line->note) <span class="muted">({{ $line->note }})</span>@endif</td><td class="r">{{ number_format((float) $line->amount, 2) }}</td></tr>
        @endforeach
        <tr class="total"><td>{{ __('Gross') }}</td><td class="r">{{ number_format((float) $payslip->gross, 2) }}</td></tr>
    </table>
    <table class="lines" style="margin-top: 10px">
        <thead><tr><th>{{ __('Deductions') }}</th><th class="r">{{ __('Amount') }}</th></tr></thead>
        @foreach ($payslip->lines->where('kind', \Modules\Payroll\Enums\ComponentKind::Deduction)->sortBy('sort_order') as $line)
            <tr><td>{{ $line->name }}@if ($line->note) <span class="muted">({{ $line->note }})</span>@endif</td><td class="r">{{ number_format((float) $line->amount, 2) }}</td></tr>
        @endforeach
        <tr class="total"><td>{{ __('Total deductions (with income tax)') }}</td><td class="r">{{ number_format((float) $payslip->deductions + (float) $payslip->tax, 2) }}</td></tr>
    </table>
    <table style="margin-top: 12px"><tr class="total"><td style="font-size: 14px">{{ __('Net pay') }}</td><td class="r" style="font-size: 14px">{{ number_format((float) $payslip->net, 2) }}</td></tr></table>
    <p class="muted" style="margin-top: 16px">{{ __('Computer-generated payslip.') }}</p>
</body>
</html>
