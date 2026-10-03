{{-- Price cell of an availability option. --}}
@if ($quote)
    <div class="fw-semibold font-monospace" data-total="{{ $quote->total }}">{{ number_format((float) $quote->total, 2) }}</div>
    @if ($quote->promotion)
        <div class="small text-success" data-promotion>{{ __(':name: −:amount', ['name' => $quote->promotion->name, 'amount' => number_format((float) $quote->promotion->amount, 2)]) }}</div>
    @endif
    @unless ($quote->fitsParty)
        <div class="small text-body-secondary">{{ __('Price for :count guests', ['count' => $quote->occupancy->total()]) }}</div>
    @endunless
@else
    <span class="text-body-secondary">—</span>
@endif
