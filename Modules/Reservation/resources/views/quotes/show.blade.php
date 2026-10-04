@php
    $money = fn (?string $amount): string => number_format((float) $amount, 2);
    $canAct = $status->isOpen() && auth()->user()?->can('update', $quote);
@endphp

<x-layouts::app :title="$quote->code" :subtitle="$guest?->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Quotes') => route('reservation.quotes.index'), $quote->code => null]">
    <x-slot:actions>
        <a href="{{ route('reservation.quotes.pdf', $quote) }}" class="btn btn-sm btn-outline-secondary" data-quote-pdf><i class="bi bi-file-earmark-pdf"></i> {{ __('PDF') }}</a>
        @if ($canAct)
            <form method="POST" action="{{ route('reservation.quotes.send', $quote) }}" class="d-inline" data-quote-send>
                @csrf
                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-envelope"></i> {{ $quote->sent_at ? __('Email again') : __('Email to guest') }}</button>
            </form>
            @can('reservation.booking.create')
                <form method="POST" action="{{ route('reservation.quotes.convert', $quote) }}" class="d-inline" data-quote-convert
                    data-confirm="{{ __('Book this quote at the quoted prices?') }}" data-confirm-text="{{ __('The rooms are locked now if they are still free.') }}">
                    @csrf
                    <button class="btn btn-sm btn-success"><i class="bi bi-check2-circle"></i> {{ __('Book it') }}</button>
                </form>
            @endcan
            <form method="POST" action="{{ route('reservation.quotes.decline', $quote) }}" class="d-inline" data-quote-decline data-confirm="{{ __('Mark this quote as declined by the guest?') }}" data-confirm-variant="danger">
                @csrf
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i> {{ __('Declined') }}</button>
            </form>
        @endif
    </x-slot:actions>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3" data-quote-header>
        <x-status-badge :status="$status" />
        <span class="text-body-secondary">{{ $quote->check_in->format('D d M Y') }} → {{ $quote->check_out->format('D d M Y') }} · {{ trans_choice(':count night|:count nights', $quote->nights()) }} · {{ $plan?->name }}</span>
        <span class="text-body-secondary">· {{ __('valid until :date', ['date' => $quote->valid_until->format('d M Y')]) }}</span>
        @if ($quote->sent_at)<span class="text-body-secondary">· {{ __('emailed :time', ['time' => $quote->sent_at->setTimezone($timezone)->format('d M Y H:i')]) }}</span>@endif
        @if ($quote->reservation_id)
            <a href="{{ route('reservation.bookings.show', $quote->reservation_id) }}" class="btn btn-sm btn-link" data-quote-booking>{{ __('Open the booking') }}</a>
        @endif
    </div>

    <div class="row">
        <div class="col-xl-7">
            <x-card :title="__('Rooms and cottages')" icon="bi-houses" body-class="p-0">
                <table class="table mb-0" data-quote-items>
                    <thead><tr><th class="ps-3">{{ __('Item') }}</th><th>{{ __('Guests') }}</th><th class="text-end">{{ __('Nights') }}</th><th class="text-end pe-3">{{ __('Total') }}</th></tr></thead>
                    <tbody>
                        @foreach ($quote->items as $item)
                            <tr>
                                <td class="ps-3"><x-status-badge :status="$item->item_type" /> {{ $item->label }}</td>
                                <td>{{ trans_choice(':count adult|:count adults', $item->adults) }}@if ($item->children), {{ trans_choice(':count child|:count children', $item->children) }}@endif</td>
                                <td class="text-end">{{ $item->nights->count() }}</td>
                                <td class="text-end pe-3 font-monospace">{{ $money($item->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
            @if ($quote->special_requests || $quote->internal_notes)
                <x-card :title="__('Notes')" icon="bi-chat-left-text">
                    @if ($quote->special_requests)<p class="mb-1"><span class="text-body-secondary">{{ __('Requests:') }}</span> {{ $quote->special_requests }}</p>@endif
                    @if ($quote->internal_notes)<p class="mb-0"><span class="text-body-secondary">{{ __('Internal:') }}</span> {{ $quote->internal_notes }}</p>@endif
                </x-card>
            @endif
        </div>
        <div class="col-xl-5">
            <x-card :title="__('Quoted price')" icon="bi-cash-stack">
                <table class="table table-sm mb-0" data-quote-totals>
                    <tr><td>{{ __('Subtotal') }}</td><td class="text-end font-monospace">{{ $money($quote->subtotal) }}</td></tr>
                    @if ($quote->discount_total !== '0.00')
                        <tr class="text-success"><td>{{ __('Discount') }}@if ($quote->promo_code) ({{ $quote->promo_code }})@endif</td><td class="text-end font-monospace">−{{ $money($quote->discount_total) }}</td></tr>
                    @endif
                    <tr><td>{{ __('Taxes') }}</td><td class="text-end font-monospace">{{ $money($quote->tax_total) }}</td></tr>
                    <tr class="fw-semibold"><td>{{ __('Grand total') }} ({{ $quote->currency_code }})</td><td class="text-end font-monospace" data-quote-total>{{ $money($quote->grand_total) }}</td></tr>
                    <tr class="table-group-divider"><td>{{ __('Deposit (:percent%)', ['percent' => rtrim(rtrim($quote->deposit_percent, '0'), '.')]) }}</td><td class="text-end font-monospace">{{ $money($quote->deposit_amount) }}</td></tr>
                </table>
                <p class="small text-body-secondary mt-2 mb-0">{{ __('Booking the quote keeps these prices; the deposit is then due by the policy\'s deadline from that moment.') }}</p>
            </x-card>
            @if ($guest)
                <x-card :title="__('Guest')" icon="bi-person">
                    <div>{{ $guest->name }}</div>
                    <div class="small text-body-secondary">{{ collect([$guest->phone, $guest->email ?? __('no email address')])->filter()->implode(' · ') }}</div>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts::app>
