@php($money = fn (?string $amount): string => number_format((float) $amount, 2))
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Invoice :no', ['no' => $invoice->invoice_no]) }}</title>
    {{-- Dompdf renders this page: simple tables and inline CSS only. --}}
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #212529; }
        h1 { font-size: 17px; margin: 0 0 2px; }
        .muted { color: #6c757d; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .grid { margin-top: 14px; }
        .grid th { text-align: left; font-size: 9px; text-transform: uppercase; color: #6c757d; border-bottom: 2px solid #adb5bd; padding: 4px 5px; }
        .grid td { padding: 4px 5px; border-bottom: 1px solid #dee2e6; }
        .right { text-align: right; }
        .totals { width: 45%; margin-left: 55%; margin-top: 10px; }
        .totals td { padding: 3px 5px; }
        .strong td { font-weight: bold; font-size: 12px; border-top: 1px solid #adb5bd; }
        .title { font-size: 15px; }
        .footer { margin-top: 24px; font-size: 9px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <h1>{{ $property?->name }}</h1>
                <div class="muted">{{ $property?->address }}</div>
                <div class="muted">{{ collect([$property?->phone, $property?->email])->filter()->implode(' · ') }}</div>
            </td>
            <td class="right">
                <div class="title"><strong>{{ __('Invoice') }}</strong></div>
                <div><strong>{{ $invoice->invoice_no }}</strong></div>
                <div class="muted">{{ $invoice->issue_date->format('d M Y') }}</div>
            </td>
        </tr>
    </table>

    <p><span class="muted">{{ __('Bill to') }}:</span> <strong>{{ $invoice->bill_to_name }}</strong>@if ($invoice->bill_to_tax_number) · {{ __('Tax no. :no', ['no' => $invoice->bill_to_tax_number]) }}@endif</p>

    <table class="grid">
        <tr><th>{{ __('Date') }}</th><th>{{ __('Description') }}</th><th class="right">{{ __('Qty') }}</th><th class="right">{{ __('Amount') }}</th><th class="right">{{ __('Tax') }}</th><th class="right">{{ __('Total') }}</th></tr>
        @foreach ($invoice->lines as $line)
            <tr>
                <td>{{ $line->service_date->format('d M') }}</td>
                <td>{{ $line->description }}</td>
                <td class="right">{{ rtrim(rtrim($line->quantity, '0'), '.') }}</td>
                <td class="right">{{ $money($line->amount) }}</td>
                <td class="right">{{ $money($line->tax_amount) }}</td>
                <td class="right">{{ $money($line->total) }}</td>
            </tr>
        @endforeach
    </table>

    <table class="totals">
        <tr><td>{{ __('Subtotal') }}</td><td class="right">{{ $money($invoice->subtotal) }}</td></tr>
        @foreach ($invoice->tax_breakdown ?? [] as $name => $amount)
            <tr><td>{{ $name }}</td><td class="right">{{ $money($amount) }}</td></tr>
        @endforeach
        <tr class="strong"><td>{{ __('Total') }} ({{ $invoice->currency_code }})</td><td class="right">{{ $money($invoice->total) }}</td></tr>
        <tr><td>{{ __('Paid (deposit included)') }}</td><td class="right">−{{ $money($invoice->paid) }}</td></tr>
        @if ($invoice->on_account !== '0.00')
            <tr><td>{{ __('Charged to account') }}</td><td class="right">−{{ $money($invoice->on_account) }}</td></tr>
        @endif
        @if ($invoice->credited !== '0.00')
            <tr><td>{{ __('Credit notes') }}</td><td class="right">−{{ $money($invoice->credited) }}</td></tr>
        @endif
        <tr class="strong"><td>{{ __('Balance') }}</td><td class="right">{{ $money($invoice->balance()) }}</td></tr>
    </table>

    <p class="footer muted">{{ __('Thank you for staying with us.') }}</p>
</body>
</html>
