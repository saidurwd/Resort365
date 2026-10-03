@php
    $money = fn (?string $amount): string => number_format((float) $amount, 2);
@endphp

<x-layouts::app :title="__('Review changes')" :subtitle="$reservation->code" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Reservations') => route('reservation.bookings.index'), $reservation->code => route('reservation.bookings.show', $reservation), __('Review changes') => null]">
    <div class="row">
        <div class="col-xl-7">
            <x-card :title="__('New stay')" icon="bi-calendar-range" body-class="p-0">
                <div class="px-3 pt-3">{{ $change->checkIn->format('D d M Y') }} → {{ $change->checkOut->format('D d M Y') }} · {{ trans_choice(':count night|:count nights', (int) $change->checkIn->diffInDays($change->checkOut)) }}</div>
                <table class="table mb-0" data-review-items>
                    <thead><tr><th class="ps-3">{{ __('Item') }}</th><th>{{ __('Guests') }}</th><th class="text-end pe-3">{{ __('Total') }}</th></tr></thead>
                    <tbody>
                        @foreach ($quote->items as $line)
                            <tr>
                                <td class="ps-3">{{ $line->label }}</td>
                                <td>{{ trans_choice(':count adult|:count adults', $line->item->adults) }}@if ($line->item->children), {{ trans_choice(':count child|:count children', $line->item->children) }}@endif</td>
                                <td class="text-end pe-3 font-monospace">{{ $money($line->quote->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        </div>
        <div class="col-xl-5">
            <x-card :title="__('Price before and after')" icon="bi-arrow-left-right">
                <table class="table table-sm mb-0" data-review-totals>
                    <thead><tr><th></th><th class="text-end">{{ __('Now') }}</th><th class="text-end">{{ __('New') }}</th></tr></thead>
                    <tr><td>{{ __('Dates') }}</td><td class="text-end">{{ $reservation->check_in->format('d M') }}–{{ $reservation->check_out->format('d M') }}</td><td class="text-end">{{ $change->checkIn->format('d M') }}–{{ $change->checkOut->format('d M') }}</td></tr>
                    <tr><td>{{ __('Subtotal') }}</td><td class="text-end font-monospace">{{ $money($reservation->subtotal) }}</td><td class="text-end font-monospace">{{ $money($quote->subtotal) }}</td></tr>
                    <tr><td>{{ __('Discount') }}</td><td class="text-end font-monospace">{{ $money($reservation->discount_total) }}</td><td class="text-end font-monospace">{{ $money($quote->discount) }}</td></tr>
                    <tr><td>{{ __('Taxes') }}</td><td class="text-end font-monospace">{{ $money($reservation->tax_total) }}</td><td class="text-end font-monospace">{{ $money($quote->tax) }}</td></tr>
                    <tr class="fw-semibold"><td>{{ __('Grand total') }}</td><td class="text-end font-monospace">{{ $money($reservation->grand_total) }}</td><td class="text-end font-monospace" data-new-total>{{ $money($quote->total) }}</td></tr>
                    <tr><td>{{ __('Deposit') }}</td><td class="text-end font-monospace">{{ $money($reservation->deposit_required) }}</td><td class="text-end font-monospace" data-new-deposit>{{ $money($quote->deposit->amount) }}</td></tr>
                    <tr><td>{{ __('Paid') }}</td><td class="text-end font-monospace" colspan="2">{{ $money($reservation->amount_paid) }}</td></tr>
                </table>
            </x-card>
            <form method="POST" action="{{ route('reservation.bookings.update', $reservation) }}" data-save-changes>
                @csrf @method('PUT')
                <input type="hidden" name="check_in" value="{{ $input['check_in'] }}">
                <input type="hidden" name="check_out" value="{{ $input['check_out'] }}">
                @foreach ($input['items'] as $index => $row)
                    @foreach (['unit', 'rate_plan', 'adults', 'children', 'remove'] as $field)
                        <input type="hidden" name="items[{{ $index }}][{{ $field }}]" value="{{ $row[$field] ?? '' }}">
                    @endforeach
                @endforeach
                <div class="d-flex gap-2">
                    <button class="btn btn-primary"><i class="bi bi-check2"></i> {{ __('Save changes') }}</button>
                    <a href="{{ route('reservation.bookings.edit', $reservation) }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
