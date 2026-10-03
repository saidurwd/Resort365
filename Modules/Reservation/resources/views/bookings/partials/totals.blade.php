{{-- Totals and deposit of a booking quote. --}}
@php($money = fn (string $amount): string => number_format((float) $amount, 2))
<table class="table table-sm mb-0" data-booking-totals>
    <tr><td>{{ __('Subtotal') }}</td><td class="text-end font-monospace">{{ $money($quote->subtotal) }}</td></tr>
    @if ((float) $quote->discount > 0)
        <tr class="text-success"><td>{{ __('Discount') }}@if ($quote->promotion) ({{ $quote->promotion->name }})@endif</td><td class="text-end font-monospace">−{{ $money($quote->discount) }}</td></tr>
    @endif
    <tr><td>{{ __('Taxes') }}</td><td class="text-end font-monospace">{{ $money($quote->tax) }}</td></tr>
    <tr class="fw-semibold"><td>{{ __('Grand total') }} ({{ $quote->currency }})</td><td class="text-end font-monospace" data-grand-total>{{ $money($quote->total) }}</td></tr>
    <tr class="table-group-divider fw-semibold"><td>{{ __('Deposit (:percent%)', ['percent' => rtrim(rtrim($quote->deposit->percent, '0'), '.')]) }}</td><td class="text-end font-monospace" data-deposit>{{ $money($quote->deposit->amount) }}</td></tr>
    <tr><td>{{ __('Balance after deposit') }}</td><td class="text-end font-monospace" data-balance>{{ $money($quote->deposit->balance) }}</td></tr>
</table>
@if ($quote->deposit->fullPaymentRequired)
    <div class="small text-warning-emphasis mt-1">{{ __('Arrival is soon: the whole stay is due now.') }}</div>
@endif
