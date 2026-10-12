@php($qty = fn (?string $amount): string => rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.'))
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Request for quotation :no', ['no' => $rfq->rfq_no]) }}</title>
    @include('procurement::documents.styles')
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
                <div class="doc-title"><strong>{{ __('Request for quotation') }}</strong></div>
                <div><strong>{{ $rfq->rfq_no }}</strong></div>
                @if ($rfq->due_on)<div class="muted">{{ __('Please reply by :date', ['date' => $rfq->due_on->format('d M Y')]) }}</div>@endif
            </td>
        </tr>
    </table>

    <h2>{{ __('To') }} {{ $vendor->name }}</h2>
    <p>{{ __('Please quote your best price per unit, and delivery time, for the items below.') }}</p>
    <table class="grid">
        <tr><th>{{ __('Item') }}</th><th class="right">{{ __('Quantity') }}</th></tr>
        @foreach ($lines as $line)
            <tr>
                <td>{{ $items[$line->item_id]->name ?? '#'.$line->item_id }} <span class="muted">{{ $items[$line->item_id]->sku ?? '' }}</span></td>
                <td class="right">{{ $qty((string) $line->quantity) }} {{ $items[$line->item_id]->baseUnit ?? '' }}</td>
            </tr>
        @endforeach
    </table>
    @if ($rfq->notes)<p>{{ $rfq->notes }}</p>@endif
    <p class="footer muted">{{ __('This is a request for prices, not an order.') }}</p>
</body>
</html>
