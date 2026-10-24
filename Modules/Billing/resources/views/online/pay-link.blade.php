{{-- A guest's payment link (Step 8.3): what is owed now and the property's gateways. --}}
<x-layouts::guest :title="__('Pay for booking :code', ['code' => $reservation->code])">
    <div class="card" data-pay-link>
        <div class="card-body">
            <p class="login-box-msg mb-1">{{ $property->name ?? config('app.name') }}</p>
            <h1 class="h5 text-center">{{ __('Booking :code', ['code' => $reservation->code]) }}</h1>
            <p class="text-center small text-body-secondary">{{ $reservation->checkIn }} – {{ $reservation->checkOut }} · {{ $reservation->guestName }}</p>

            @if ($payment === 'paid')
                <div class="alert alert-success" data-paid>{{ __('Thank you, your payment was received.') }}</div>
            @elseif ($payment === 'pending')
                <div class="alert alert-info">{{ __('Your payment is being confirmed. You will receive an email once it is done.') }}</div>
            @elseif (in_array($payment, ['failed', 'review'], true))
                <div class="alert alert-warning">{{ __('The payment did not go through. You can try again.') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            @if ((float) $amount > 0 && $options !== [])
                <p class="text-center mb-1">{{ __('To pay now (:what)', ['what' => strtolower($purpose->label())]) }}</p>
                <p class="text-center fs-3 fw-semibold" data-amount="{{ $amount }}">{{ $reservation->currencyCode }} {{ number_format((float) $amount, 2) }}</p>
                <form method="POST" action="{{ $action }}" class="d-grid gap-2">
                    @csrf
                    @foreach ($options as $option)
                        <button name="provider" value="{{ $option->provider->value }}" class="btn btn-primary" data-pay-with="{{ $option->provider->value }}">
                            {{ __('Pay with :gateway', ['gateway' => $option->label]) }}@if ($option->sandbox) <span class="badge text-bg-warning">{{ __('test') }}</span>@endif
                        </button>
                    @endforeach
                </form>
            @elseif ((float) $amount <= 0)
                <p class="text-center mb-0" data-nothing-due>{{ __('Nothing is due for this booking now.') }}</p>
            @else
                <p class="text-center mb-0">{{ __('Online payment is not available at the moment. Please contact the resort.') }}</p>
            @endif
        </div>
    </div>
</x-layouts::guest>
