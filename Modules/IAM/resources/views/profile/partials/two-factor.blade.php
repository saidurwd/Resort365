{{--
    Fortify two-factor authentication (TOTP). States: off → pending (scan + confirm) → on.
    Enabling, disabling and regenerating codes ask for the password first (password.confirm).
--}}
<x-card :title="__('Two-factor authentication')" icon="bi-shield-lock">
    @if ($user->hasTwoFactorEnabled())
        <p><span class="badge text-bg-success me-1">{{ __('On') }}</span> {{ __('Two-factor authentication is on. You will be asked for a code from your authenticator app when you sign in.') }}</p>

        @if (in_array(session('status'), ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true))
            <div class="alert alert-warning">
                <p class="mb-2">{{ __('Store these recovery codes somewhere safe. Each one can be used once if you lose your device.') }}</p>
                <div class="row row-cols-2 g-1 font-monospace small" data-recovery-codes>
                    @foreach ($user->recoveryCodes() as $code)
                        <div class="col">{{ $code }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat"></i> {{ __('New recovery codes') }}</button>
            </form>
            <form method="POST" action="{{ route('two-factor.disable') }}" data-confirm="{{ __('Turn off two-factor authentication?') }}" data-confirm-text="{{ __('Your account will be protected by your password only.') }}" data-confirm-variant="danger" data-confirm-button="{{ __('Turn off') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-shield-x"></i> {{ __('Turn off') }}</button>
            </form>
        </div>
    @elseif ($user->two_factor_secret)
        <p>{{ __('Scan this QR code with an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password…), then enter the 6-digit code it shows.') }}</p>

        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
            {{-- SVG generated server-side by Fortify from the user's own secret. --}}
            <div class="bg-white p-2 rounded border" data-two-factor-qr>{!! $user->twoFactorQrCodeSvg() !!}</div>
            <div class="small">
                <div class="text-body-secondary">{{ __('Setup key') }}</div>
                <code class="user-select-all" data-two-factor-secret>{{ decrypt($user->two_factor_secret) }}</code>
            </div>
        </div>

        <form method="POST" action="{{ route('two-factor.confirm') }}" class="d-flex flex-wrap align-items-start gap-2">
            @csrf
            <x-form.input name="code" :label="__('Authentication code')" inputmode="numeric" autocomplete="one-time-code" required error-bag="confirmTwoFactorAuthentication" wrapper-class="mb-0 flex-grow-1" />
            <div class="d-flex gap-2 align-self-end">
                <button type="submit" class="btn btn-primary">{{ __('Confirm') }}</button>
            </div>
        </form>
        <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-2">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-link btn-sm px-0">{{ __('Cancel setup') }}</button>
        </form>
    @else
        <p>{{ __('Add a second step to sign-in: a 6-digit code from an authenticator app on your phone.') }}</p>
        <form method="POST" action="{{ route('two-factor.enable') }}">
            @csrf
            <button type="submit" class="btn btn-primary"><i class="bi bi-shield-plus"></i> {{ __('Turn on two-factor authentication') }}</button>
        </form>
        <p class="form-text mb-0">{{ __('You may be asked to confirm your password first.') }}</p>
    @endif
</x-card>
