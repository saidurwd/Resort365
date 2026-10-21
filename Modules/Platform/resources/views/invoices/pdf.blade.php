<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Invoice :no', ['no' => $invoice->number]) }}</title>
    {{-- Dompdf renders this page: simple tables and inline CSS only. --}}
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #212529; }
        h1 { font-size: 17px; margin: 0 0 2px; }
        .muted { color: #6c757d; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .grid { margin-top: 18px; }
        .grid th { text-align: left; font-size: 9px; text-transform: uppercase; color: #6c757d; border-bottom: 2px solid #adb5bd; padding: 4px 5px; }
        .grid td { padding: 6px 5px; border-bottom: 1px solid #dee2e6; }
        .right { text-align: right; }
        .totals { width: 45%; margin-left: 55%; margin-top: 10px; }
        .totals td { padding: 3px 5px; }
        .strong td { font-weight: bold; font-size: 12px; border-top: 1px solid #adb5bd; }
        .footer { margin-top: 24px; font-size: 9.5px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <h1>{{ config('platform.billing.issuer') }}</h1>
                <div class="muted">{{ __('Billed to') }}: <strong>{{ $tenant->name }}</strong></div>
                <div class="muted">{{ $tenant->email }} · {{ $tenant->domain() }}</div>
            </td>
            <td class="right">
                <div><strong>{{ __('Invoice') }} {{ $invoice->number }}</strong></div>
                <div class="muted">{{ __('Issued') }} {{ $invoice->issued_on->format('d M Y') }}</div>
                <div class="muted">{{ __('Due') }} {{ $invoice->due_on->format('d M Y') }}</div>
                <div><strong>{{ $invoice->status->label() }}</strong></div>
            </td>
        </tr>
    </table>

    <table class="grid">
        <thead><tr><th>{{ __('Description') }}</th><th>{{ __('Period') }}</th><th class="right">{{ __('Amount') }} ({{ $invoice->currency_code }})</th></tr></thead>
        <tbody>
            <tr>
                <td>{{ $invoice->description }}</td>
                <td>{{ $invoice->period_start?->format('d M Y') }} – {{ $invoice->period_end?->format('d M Y') }}</td>
                <td class="right">{{ $invoice->amount }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr><td>{{ __('Total') }}</td><td class="right">{{ $invoice->amount }}</td></tr>
        <tr><td>{{ __('Paid') }}</td><td class="right">{{ $invoice->paid_amount }}</td></tr>
        <tr class="strong"><td>{{ __('Balance due') }}</td><td class="right">{{ $invoice->currency_code }} {{ $invoice->balance() }}</td></tr>
    </table>

    <div class="footer muted">{{ config('platform.billing.payment_instructions') }}</div>
</body>
</html>
