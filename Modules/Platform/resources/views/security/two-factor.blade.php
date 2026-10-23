<x-layouts::platform :title="__('Two-factor sign-in')" :breadcrumbs="[__('Platform') => route('platform.console'), __('Two-factor sign-in') => null]">
    <div class="row">
        <div class="col-lg-7">
            @if ($admin->hasEnabledTwoFactorAuthentication())
                <x-card :title="__('Two-factor sign-in is on')" icon="bi-shield-check" data-two-factor-on>
                    <p>{{ __('You sign in with your password and a code from your authenticator app.') }}</p>
                    @if (session('show_codes'))
                        <p class="mb-2">{{ __('Recovery codes: each works once if you lose your phone. Store them in your password manager now; they are not shown again.') }}</p>
                        <ul class="list-unstyled font-monospace row row-cols-2 mb-0" data-recovery-codes>
                            @foreach ($admin->recoveryCodes() as $code)
                                <li class="col">{{ $code }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="small text-body-secondary mb-0">{{ __('Lost your phone and your recovery codes? Another admin runs `php artisan platform:reset-two-factor` with your email address.') }}</p>
                    @endif
                </x-card>
            @else
                <x-card :title="__('Set up two-factor sign-in')" icon="bi-shield-lock" data-two-factor-setup>
                    @if ($required)
                        <div class="alert alert-warning">{{ __('Platform admins must sign in with a second factor. Set it up to continue.') }}</div>
                    @endif
                    <ol class="mb-3">
                        <li>{{ __('Scan this code with an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password…).') }}</li>
                        <li>{{ __('Type the 6-digit code it shows.') }}</li>
                    </ol>
                    <div class="bg-white d-inline-block p-2 mb-2">{!! $admin->twoFactorQrCodeSvg() !!}</div>
                    <p class="small text-body-secondary">{{ __('Or enter this key by hand:') }} <span class="font-monospace" data-secret>{{ decrypt((string) $admin->two_factor_secret) }}</span></p>
                    <form method="POST" action="{{ route('platform.two-factor.confirm') }}" class="d-flex gap-2 align-items-start">
                        @csrf
                        <x-form.input name="code" :label="__('Code')" inputmode="numeric" autocomplete="one-time-code" required wrapper-class="mb-0" />
                        <button type="submit" class="btn btn-primary mt-4">{{ __('Turn on') }}</button>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts::platform>
