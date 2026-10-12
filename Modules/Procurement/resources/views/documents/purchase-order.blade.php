@php($money = fn (?string $amount): string => number_format((float) $amount, 2))
@php($qty = fn (?string $amount): string => rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.'))
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Purchase order :no', ['no' => $order->order_no]) }}</title>
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
                <div class="doc-title"><strong>{{ __('Purchase order') }}</strong></div>
                <div><strong>{{ $order->order_no }}</strong></div>
                <div class="muted">{{ __('Date :date', ['date' => $order->ordered_on->format('d M Y')]) }}</div>
            </td>
        </tr>
    </table>

    <h2>{{ __('Vendor') }}</h2>
    <table class="grid">
        <tr><td class="muted">{{ __('Name') }}</td><td>{{ $vendor->name }}</td></tr>
        @if ($vendor->tax_id)<tr><td class="muted">{{ __('Tax ID') }}</td><td>{{ $vendor->tax_id }}</td></tr>@endif
        @if ($vendor->address)<tr><td class="muted">{{ __('Address') }}</td><td>{{ $vendor->address }}</td></tr>@endif
        <tr><td class="muted">{{ __('Expected delivery') }}</td><td>{{ $order->expected_on?->format('l, d M Y') ?? '—' }}</td></tr>
        <tr><td class="muted">{{ __('Payment terms') }}</td><td>{{ trans_choice(':count day|:count days', $order->payment_terms_days) }}</td></tr>
        @if ($department)<tr><td class="muted">{{ __('Department') }}</td><td>{{ $department }}</td></tr>@endif
    </table>

    <h2>{{ __('Items') }} ({{ $order->currency }})</h2>
    <table class="grid">
        <tr><th>{{ __('Item') }}</th><th class="right">{{ __('Quantity') }}</th><th class="right">{{ __('Unit price') }}</th><th class="right">{{ __('Tax') }}</th><th class="right">{{ __('Amount') }}</th></tr>
        @foreach ($order->lines as $line)
            <tr>
                <td>{{ $items[$line->item_id]->name ?? '#'.$line->item_id }} <span class="muted">{{ $items[$line->item_id]->sku ?? '' }}</span></td>
                <td class="right">{{ $qty((string) $line->quantity) }} {{ $items[$line->item_id]->baseUnit ?? '' }}</td>
                <td class="right">{{ number_format((float) $line->unit_price, 4) }}</td>
                <td class="right">{{ $money((string) $line->tax_amount) }}</td>
                <td class="right">{{ $money((string) $line->line_total) }}</td>
            </tr>
        @endforeach
        <tr><td colspan="4">{{ __('Subtotal') }}</td><td class="right">{{ $money((string) $order->subtotal) }}</td></tr>
        <tr><td colspan="4">{{ __('Taxes') }}</td><td class="right">{{ $money((string) $order->tax_total) }}</td></tr>
        <tr class="total"><td colspan="4">{{ __('Total') }}</td><td class="right">{{ $money((string) $order->total) }}</td></tr>
    </table>

    @if ($order->notes)<p>{{ $order->notes }}</p>@endif
    <p class="footer muted">{{ __('Please confirm receipt of this order and quote the order number on your delivery note and invoice.') }}</p>
</body>
</html>
