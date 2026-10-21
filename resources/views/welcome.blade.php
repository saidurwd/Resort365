{{-- The central domain's home page: what the product is, and the way to sign up (Step 7.4). --}}
<x-layouts::guest :title="__('Resort management')" wide>
    <h1 class="h3">{{ __('Run your resort in one place') }}</h1>
    <p class="text-body-secondary">{{ __('Bookings and the tape chart, the front desk, billing, housekeeping, the restaurant POS, stores, purchasing, accounts, HR and payroll, for one resort or many.') }}</p>

    @if (Route::has('platform.signup.create'))
        <a href="{{ route('platform.signup.create') }}" class="btn btn-primary w-100 mb-2">{{ __('Start your free trial') }}</a>
    @endif
    <p class="small text-body-secondary mb-0">{{ __('Already with us? Sign in at your own address, e.g. yourresort.:domain.', ['domain' => config('tenancy.central_domain')]) }}</p>
</x-layouts::guest>
