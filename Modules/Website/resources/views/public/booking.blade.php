@extends('website::public.layout')

@section('title', __('Booking :code', ['code' => $reservation->code]))

@php
    use Modules\Website\Services\Prices;
    $date = fn (?string $day): string => $day ? \Carbon\CarbonImmutable::parse($day)->locale(app()->getLocale())->translatedFormat('D j M Y') : '';
    $status = $reservation->status->value;
@endphp

@section('content')
    <section class="website-page-head">
        <div class="container">
            <h1>{{ __('Booking :code', ['code' => $reservation->code]) }}</h1>
            <p class="lead mb-0" data-booking-status="{{ $status }}">
                @switch($status)
                    @case('tentative') {{ __('Received: your rooms are held until the payment arrives.') }} @break
                    @case('confirmed') {{ __('Confirmed. We look forward to welcoming you.') }} @break
                    @case('cancelled') {{ __('Cancelled.') }} @break
                    @default {{ $reservation->status->label() }}
                @endswitch
            </p>
        </div>
    </section>
    <section class="website-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="website-policy">
                        <h2 class="h5">{{ __('Your stay') }}</h2>
                        <p class="mb-1">{{ $date($reservation->checkIn) }} – {{ $date($reservation->checkOut) }}</p>
                        <p class="mb-1">{{ implode(', ', array_map('strval', $reservation->units)) }}</p>
                        <p class="mb-0 small text-body-secondary">{{ __('Booked for :name', ['name' => $reservation->guestName]) }}</p>
                    </div>

                    @if ($status === 'tentative')
                        <div class="website-policy" data-pay-now>
                            <h2 class="h5">{{ __('To confirm your booking') }}</h2>
                            <p class="mb-2">{{ __('Please pay :amount', ['amount' => Prices::format($booking->amount_due_now, $currency)]) }}@if ($reservation->depositDueAt) {{ __('by :time', ['time' => \Carbon\CarbonImmutable::parse($reservation->depositDueAt)->setTimezone($property->timezone ?? 'UTC')->locale(app()->getLocale())->translatedFormat('j M Y, H:i')]) }}@endif.</p>
                            @if ($instructions)
                                <p class="mb-0">{!! nl2br(e($instructions)) !!}</p>
                            @endif
                        </div>
                    @endif

                    @if ($cancellation)
                        <div class="website-policy" data-cancel>
                            <h2 class="h5">{{ __('Cancel this booking') }}</h2>
                            <p>{{ (float) $cancellation->fee > 0 ? __('Cancelling now costs :fee.', ['fee' => Prices::format($cancellation->fee, $currency)]) : __('Cancelling now is free of charge.') }}
                                @if ((float) $cancellation->refund > 0) {{ __('We will refund :amount.', ['amount' => Prices::format($cancellation->refund, $currency)]) }} @endif</p>
                            <form method="POST" action="{{ $cancelUrl }}">
                                @csrf
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="confirm" id="confirm-cancel" value="1" required>
                                    <label class="form-check-label" for="confirm-cancel">{{ __('Yes, cancel my booking.') }}</label>
                                </div>
                                <button type="submit" class="btn btn-outline-danger" data-cancel-button>{{ __('Cancel booking') }}</button>
                            </form>
                        </div>
                    @endif
                </div>
                <aside class="col-lg-5">
                    <div class="website-cart">
                        <h2 class="h5">{{ __('Price') }}</h2>
                        <dl class="website-cart-totals mb-0">
                            <dt class="fw-bold">{{ __('Total') }}</dt><dd class="fw-bold">{{ Prices::format($reservation->grandTotal, $currency) }}</dd>
                            <dt>{{ __('Paid') }}</dt><dd>{{ Prices::format($reservation->amountPaid, $currency) }}</dd>
                            <dt>{{ __('Balance') }}</dt><dd>{{ Prices::format($reservation->balanceDue, $currency) }}</dd>
                        </dl>
                        <p class="small text-body-secondary mt-3 mb-0">{{ __('Keep this page: the link is also in your email.') }}</p>
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endsection
