@php($money = fn (?string $amount): string => number_format((float) $amount, 2))
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Quotation :code', ['code' => $quote->code]) }}</title>
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
                <div class="doc-title"><strong>{{ __('Quotation') }}</strong></div>
                <div><strong>{{ $quote->code }}</strong></div>
                <div class="muted">{{ __('Valid until :date', ['date' => $quote->valid_until->format('d M Y')]) }}</div>
            </td>
        </tr>
    </table>

    <h2>{{ __('For') }} {{ $guest?->name }}</h2>
    <table class="grid">
        <tr><td class="muted">{{ __('Check-in') }}</td><td>{{ $quote->check_in->format('l, d M Y') }}</td></tr>
        <tr><td class="muted">{{ __('Check-out') }}</td><td>{{ $quote->check_out->format('l, d M Y') }}</td></tr>
        <tr><td class="muted">{{ __('Nights') }}</td><td>{{ $quote->nights() }}</td></tr>
        <tr><td class="muted">{{ __('Rate plan') }}</td><td>{{ $plan?->name }}</td></tr>
    </table>

    <h2>{{ __('Price') }} ({{ $quote->currency_code }})</h2>
    <table class="grid">
        <tr><th>{{ __('Accommodation') }}</th><th>{{ __('Guests') }}</th><th class="right">{{ __('Amount') }}</th></tr>
        @foreach ($items as $item)
            <tr>
                <td>{{ $item['label'] }}</td>
                <td>{{ trans_choice(':count adult|:count adults', $item['adults']) }}@if ($item['children']), {{ trans_choice(':count child|:count children', $item['children']) }}@endif</td>
                <td class="right">{{ $money($item['total']) }}</td>
            </tr>
        @endforeach
        <tr><td colspan="2">{{ __('Subtotal') }}</td><td class="right">{{ $money($quote->subtotal) }}</td></tr>
        @if ($quote->discount_total !== '0.00')
            <tr><td colspan="2">{{ __('Discount') }}@if ($quote->promo_code) ({{ $quote->promo_code }})@endif</td><td class="right">−{{ $money($quote->discount_total) }}</td></tr>
        @endif
        <tr><td colspan="2">{{ __('Taxes') }}</td><td class="right">{{ $money($quote->tax_total) }}</td></tr>
        <tr class="total"><td colspan="2">{{ __('Grand total') }}</td><td class="right">{{ $money($quote->grand_total) }}</td></tr>
        <tr><td colspan="2">{{ __('Deposit to confirm (:percent%)', ['percent' => rtrim(rtrim($quote->deposit_percent, '0'), '.')]) }}</td><td class="right">{{ $money($quote->deposit_amount) }}</td></tr>
    </table>

    <p class="footer muted">{{ __('Prices are held until the date above; rooms are reserved only once you confirm. Reply to this email or call us to book.') }}</p>
</body>
</html>
