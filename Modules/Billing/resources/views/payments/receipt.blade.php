<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Receipt :number', ['number' => $payment->receipt_no]) }}</title>
    {{-- Dompdf renders this page: simple tables and inline CSS only (no Bootstrap, no flexbox). --}}
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #212529; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .muted { color: #6c757d; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .lines { margin-top: 14px; }
        .lines td { padding: 5px 0; border-bottom: 1px solid #dee2e6; }
        .amount { font-size: 18px; font-weight: bold; text-align: right; }
        .right { text-align: right; }
        .footer { margin-top: 24px; font-size: 9px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <h1>{{ $property?->name }}</h1>
                <div class="muted">{{ __('Payment receipt') }}</div>
            </td>
            <td class="right">
                <div><strong>{{ $payment->receipt_no }}</strong></div>
                <div class="muted">{{ $payment->received_at->setTimezone($timezone)->format('d M Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <tr><td class="muted">{{ __('Received from') }}</td><td class="right">{{ $guest?->name ?? '—' }}</td></tr>
        @if ($reservation)
            <tr><td class="muted">{{ __('Booking') }}</td><td class="right">{{ $reservation->code }} · {{ \Carbon\CarbonImmutable::parse($reservation->checkIn)->format('d M Y') }} → {{ \Carbon\CarbonImmutable::parse($reservation->checkOut)->format('d M Y') }}</td></tr>
        @endif
        <tr><td class="muted">{{ __('For') }}</td><td class="right">{{ $payment->payment_type->label() }}</td></tr>
        <tr><td class="muted">{{ __('Method') }}</td><td class="right">{{ $payment->method->label() }}@if ($payment->reference) · {{ $payment->reference }}@endif</td></tr>
        @if ($payment->notes)
            <tr><td class="muted">{{ __('Notes') }}</td><td class="right">{{ $payment->notes }}</td></tr>
        @endif
        <tr><td class="muted">{{ __('Amount received') }}</td><td class="amount">{{ $payment->currency_code }} {{ number_format((float) $payment->amount, 2) }}</td></tr>
        @if ($reservation)
            <tr><td class="muted">{{ __('Booking total / paid / balance') }}</td><td class="right">{{ number_format((float) $reservation->grandTotal, 2) }} / {{ number_format((float) $reservation->amountPaid, 2) }} / {{ number_format((float) $reservation->balanceDue, 2) }}</td></tr>
        @endif
    </table>

    <p class="footer muted">{{ __('Received by :name.', ['name' => $receivedBy ?? __('staff')]) }} {{ __('This receipt was generated electronically.') }}</p>
</body>
</html>
