@php($money = fn (?string $amount): string => number_format((float) $amount, 2))
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Booking confirmation :code', ['code' => $reservation->code]) }}</title>
    @include('reservation::documents.styles')
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
                <div class="doc-title"><strong>{{ __('Booking confirmation') }}</strong></div>
                <div><strong>{{ $reservation->code }}</strong></div>
                <div class="muted">{{ $reservation->status->label() }}</div>
            </td>
        </tr>
    </table>

    <h2>{{ __('Stay') }}</h2>
    <table class="grid">
        <tr><td class="muted">{{ __('Guest') }}</td><td>{{ $guest?->name }}</td></tr>
        <tr><td class="muted">{{ __('Check-in') }}</td><td>{{ $reservation->check_in->format('l, d M Y') }} · {{ __('from :time', ['time' => $property?->checkInTime]) }}</td></tr>
        <tr><td class="muted">{{ __('Check-out') }}</td><td>{{ $reservation->check_out->format('l, d M Y') }} · {{ __('by :time', ['time' => $property?->checkOutTime]) }}</td></tr>
        <tr><td class="muted">{{ __('Nights') }}</td><td>{{ $reservation->nights() }}</td></tr>
        <tr><td class="muted">{{ __('Rate plan') }}</td><td>{{ $plan?->name }}</td></tr>
        @if (count($occupants) > 1)
            <tr><td class="muted">{{ __('Guests') }}</td><td>{{ implode(', ', $occupants) }}</td></tr>
        @endif
    </table>

    <h2>{{ __('Rooms and cottages') }}</h2>
    <table class="grid">
        <tr><th>{{ __('Accommodation') }}</th><th>{{ __('Guests') }}</th></tr>
        @foreach ($items as $item)
            <tr><td>{{ $item['label'] }}</td><td>{{ trans_choice(':count adult|:count adults', $item['adults']) }}@if ($item['children']), {{ trans_choice(':count child|:count children', $item['children']) }}@endif</td></tr>
        @endforeach
    </table>

    <h2>{{ __('Payment') }} ({{ $reservation->currency_code }})</h2>
    <table class="grid">
        <tr><td>{{ __('Grand total (taxes included)') }}</td><td class="right">{{ $money($reservation->grand_total) }}</td></tr>
        <tr><td>{{ __('Paid') }}</td><td class="right">{{ $money($reservation->amount_paid) }}</td></tr>
        <tr class="total"><td>{{ __('Balance due') }}@if ($reservation->balance_due_on) · {{ __('by :date', ['date' => $reservation->balance_due_on->format('d M Y')]) }}@endif</td><td class="right">{{ $money($reservation->balance_due) }}</td></tr>
    </table>

    @if ($reservation->special_requests)
        <div class="box"><strong>{{ __('Your requests') }}:</strong> {{ $reservation->special_requests }}</div>
    @endif

    <p class="footer muted">{{ __('Please show this confirmation at check-in with a photo ID. We look forward to welcoming you.') }}</p>
</body>
</html>
