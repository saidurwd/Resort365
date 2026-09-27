<x-layouts::guest :title="__('Two-factor authentication')">
    <div x-data="{ recovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">
        <p class="login-box-msg" x-show="! recovery">{{ __('Enter the 6-digit code from your authenticator app.') }}</p>
        <p class="login-box-msg" x-show="recovery" x-cloak>{{ __('Enter one of your emergency recovery codes.') }}</p>

        <form method="POST" action="{{ route('two-factor.login.store') }}">
            @csrf
            <div x-show="! recovery">
                <x-form.input name="code" :label="__('Authentication code')" inputmode="numeric" autocomplete="one-time-code" autofocus x-bind:disabled="recovery" />
            </div>
            <div x-show="recovery" x-cloak>
                <x-form.input name="recovery_code" :label="__('Recovery code')" autocomplete="one-time-code" x-bind:disabled="! recovery" />
            </div>

            <button type="submit" class="btn btn-primary w-100">{{ __('Continue') }}</button>
        </form>

        <p class="mt-3 mb-0 text-center small">
            <button type="button" class="btn btn-link btn-sm p-0" x-show="! recovery" x-on:click="recovery = true">{{ __('Use a recovery code') }}</button>
            <button type="button" class="btn btn-link btn-sm p-0" x-show="recovery" x-cloak x-on:click="recovery = false">{{ __('Use an authentication code') }}</button>
        </p>
    </div>
</x-layouts::guest>
