{{-- A unit's "from" price: $from = ['amount' => …, 'plus_tax' => bool] or null; $currency. --}}
@if ($from)
    <div class="website-price" data-from-price>
        <span class="website-price-label">{{ __('From') }}</span>
        <span class="website-price-amount">{{ \Modules\Website\Services\Prices::format($from['amount'], $currency) }}</span>
        <span class="website-price-unit">{{ __('per night') }}@if ($from['plus_tax']) · {{ __('plus taxes') }}@endif</span>
    </div>
@endif
