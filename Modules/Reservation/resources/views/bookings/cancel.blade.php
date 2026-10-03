@php
    $money = fn (?string $amount): string => number_format((float) $amount, 2);
@endphp

<x-layouts::app :title="__('Cancel booking')" :subtitle="$reservation->code" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Reservations') => route('reservation.bookings.index'), $reservation->code => route('reservation.bookings.show', $reservation), __('Cancel') => null]">
    <div class="row">
        <div class="col-lg-7">
            <x-card :title="__('Cancel :code', ['code' => $reservation->code])" icon="bi-x-circle" variant="danger">
                <p>{{ $guest?->name }} · {{ $reservation->check_in->format('D d M Y') }} → {{ $reservation->check_out->format('D d M Y') }}</p>
                <table class="table table-sm" data-cancellation-quote>
                    <tr><td>{{ __('Grand total') }}</td><td class="text-end font-monospace">{{ $money($reservation->grand_total) }}</td></tr>
                    <tr><td>{{ __('Paid') }}</td><td class="text-end font-monospace">{{ $money($reservation->amount_paid) }}</td></tr>
                    <tr class="fw-semibold"><td>{{ __('Cancellation fee') }}</td><td class="text-end font-monospace" data-fee>{{ $money($quote->fee) }}</td></tr>
                    <tr><td>{{ __('Refund due') }}</td><td class="text-end font-monospace" data-refund>{{ $money($quote->refund) }}</td></tr>
                    @if ($quote->owed !== '0.00')
                        <tr><td>{{ __('Still owed by the guest') }}</td><td class="text-end font-monospace">{{ $money($quote->owed) }}</td></tr>
                    @endif
                </table>
                <p class="small text-body-secondary">
                    @if ($quote->rule)
                        {{ __('The cancellation policy charges this fee for cancelling now.') }}
                    @else
                        {{ __('No cancellation fee applies today.') }}
                    @endif
                    {{ __('The rooms are released at once. Refunds are paid from Billing.') }}
                </p>
                <form method="POST" action="{{ route('reservation.bookings.cancel.store', $reservation) }}" data-cancel-form>
                    @csrf
                    <x-form.input name="reason" :label="__('Reason')" required maxlength="500" />
                    <div class="d-flex gap-2">
                        <button class="btn btn-danger"><i class="bi bi-x-circle"></i> {{ __('Cancel booking') }}</button>
                        <a href="{{ route('reservation.bookings.show', $reservation) }}" class="btn btn-outline-secondary">{{ __('Keep it') }}</a>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts::app>
