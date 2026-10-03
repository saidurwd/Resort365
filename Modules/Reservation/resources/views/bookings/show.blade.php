@php($money = fn (string $amount): string => number_format((float) $amount, 2))

<x-layouts::app :title="$reservation->code" :subtitle="$guest?->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), $reservation->code => null]">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3" data-reservation-header>
        <x-status-badge :status="$reservation->status" />
        <x-status-badge :status="$reservation->payment_status" />
        <span class="text-body-secondary">{{ $reservation->check_in->format('D d M Y') }} → {{ $reservation->check_out->format('D d M Y') }} · {{ trans_choice(':count night|:count nights', $reservation->nights()) }} · {{ $plan?->name }}</span>
    </div>
    <div class="row">
        <div class="col-xl-7">
            <x-card :title="__('Rooms and cottages')" icon="bi-houses" body-class="p-0">
                <table class="table mb-0" data-reservation-items>
                    <thead><tr><th class="ps-3">{{ __('Item') }}</th><th>{{ __('Guests') }}</th><th class="text-end">{{ __('Nights') }}</th><th class="text-end pe-3">{{ __('Total') }}</th></tr></thead>
                    <tbody>
                        @foreach ($reservation->items as $item)
                            <tr>
                                <td class="ps-3">
                                    <x-status-badge :status="$item->item_type" />
                                    {{ $item->room_id ? ($labels['room:'.$item->room_id] ?? '') : ($labels['cottage:'.$item->cottage_id] ?? '') }}
                                </td>
                                <td>{{ trans_choice(':count adult|:count adults', $item->adults) }}@if ($item->children), {{ trans_choice(':count child|:count children', $item->children) }}@endif</td>
                                <td class="text-end">{{ $item->nights->count() }}</td>
                                <td class="text-end pe-3 font-monospace">{{ $money($item->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
            @if ($reservation->special_requests || $reservation->internal_notes)
                <x-card :title="__('Notes')" icon="bi-chat-left-text">
                    @if ($reservation->special_requests)<p class="mb-1"><span class="text-body-secondary">{{ __('Requests:') }}</span> {{ $reservation->special_requests }}</p>@endif
                    @if ($reservation->internal_notes)<p class="mb-0"><span class="text-body-secondary">{{ __('Internal:') }}</span> {{ $reservation->internal_notes }}</p>@endif
                </x-card>
            @endif
        </div>
        <div class="col-xl-5">
            <x-card :title="__('Price and deposit')" icon="bi-cash-stack">
                <table class="table table-sm mb-0" data-reservation-totals>
                    <tr><td>{{ __('Subtotal') }}</td><td class="text-end font-monospace">{{ $money($reservation->subtotal) }}</td></tr>
                    @if ((float) $reservation->discount_total > 0)
                        <tr class="text-success"><td>{{ __('Discount') }}@if ($reservation->promo_code) ({{ $reservation->promo_code }})@endif</td><td class="text-end font-monospace">−{{ $money($reservation->discount_total) }}</td></tr>
                    @endif
                    <tr><td>{{ __('Taxes') }}</td><td class="text-end font-monospace">{{ $money($reservation->tax_total) }}</td></tr>
                    <tr class="fw-semibold"><td>{{ __('Grand total') }} ({{ $reservation->currency_code }})</td><td class="text-end font-monospace">{{ $money($reservation->grand_total) }}</td></tr>
                    <tr class="table-group-divider fw-semibold"><td>{{ __('Deposit (:percent%)', ['percent' => rtrim(rtrim($reservation->deposit_percent, '0'), '.')]) }}</td><td class="text-end font-monospace" data-deposit>{{ $money($reservation->deposit_required) }}</td></tr>
                    <tr><td>{{ __('Paid') }}</td><td class="text-end font-monospace">{{ $money($reservation->amount_paid) }}</td></tr>
                    <tr><td>{{ __('Balance') }}</td><td class="text-end font-monospace">{{ $money($reservation->balance_due) }}</td></tr>
                </table>
                @if ($reservation->deposit_due_at)
                    <div class="small mt-2" data-deposit-due>{{ __('Deposit due by :time', ['time' => $reservation->deposit_due_at->setTimezone($timezone)->format('d M Y H:i')]) }}@if ($reservation->auto_cancel_unpaid) · {{ __('cancelled automatically if unpaid') }}@endif</div>
                @endif
            </x-card>
            <a href="{{ route('reservation.bookings.create') }}" class="btn btn-outline-primary"><i class="bi bi-plus-lg"></i> {{ __('Another booking') }}</a>
        </div>
    </div>
</x-layouts::app>
