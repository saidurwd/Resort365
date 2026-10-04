<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Credit note :no', ['no' => $note->credit_note_no]) }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #212529; }
        h1 { font-size: 17px; margin: 0 0 2px; }
        .muted { color: #6c757d; }
        table { width: 100%; border-collapse: collapse; }
        .lines td { padding: 5px 0; border-bottom: 1px solid #dee2e6; }
        .right { text-align: right; }
        .title { font-size: 15px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td><h1>{{ $property?->name }}</h1><div class="muted">{{ $property?->address }}</div></td>
            <td class="right"><div class="title"><strong>{{ __('Credit note') }}</strong></div><div><strong>{{ $note->credit_note_no }}</strong></div><div class="muted">{{ $note->issue_date->format('d M Y') }}</div></td>
        </tr>
    </table>
    <table class="lines">
        <tr><td class="muted">{{ __('For invoice') }}</td><td class="right">{{ $invoice->invoice_no }} · {{ $invoice->issue_date->format('d M Y') }}</td></tr>
        <tr><td class="muted">{{ __('Bill to') }}</td><td class="right">{{ $invoice->bill_to_name }}</td></tr>
        <tr><td class="muted">{{ __('Reason') }}</td><td class="right">{{ $note->reason }}</td></tr>
        <tr><td class="muted">{{ __('Amount credited') }}</td><td class="right"><strong>{{ $invoice->currency_code }} {{ number_format((float) $note->amount, 2) }}</strong></td></tr>
    </table>
</body>
</html>
