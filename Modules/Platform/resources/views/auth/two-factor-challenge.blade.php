<x-layouts::guest :title="__('Two-factor sign-in')">
    <p class="login-box-msg">{{ __('Type the 6-digit code from your authenticator app.') }}</p>

    <form method="POST" action="{{ route('platform.two-factor.verify') }}" x-data="{ recovery: false }">
        @csrf
        <div x-show="! recovery">
            <x-form.input name="code" :label="__('Code')" inputmode="numeric" autocomplete="one-time-code" autofocus />
        </div>
        <div x-show="recovery" x-cloak>
            <x-form.input name="recovery_code" :label="__('Recovery code')" autocomplete="off" />
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <button type="button" class="btn btn-link px-0" x-on:click="recovery = ! recovery" x-text="recovery ? @js(__('Use the app\'s code')) : @js(__('Use a recovery code'))"></button>
            <button type="submit" class="btn btn-primary">{{ __('Sign in') }}</button>
        </div>
    </form>
</x-layouts::guest>
