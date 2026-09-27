<x-layouts::guest :title="__('Confirm password')">
    <p class="login-box-msg">{{ __('This is a secure area. Please confirm your password to continue.') }}</p>

    <form method="POST" action="{{ route('password.confirm.store') }}">
        @csrf
        <x-form.input name="password" type="password" :label="__('Password')" required autofocus autocomplete="current-password" />
        <button type="submit" class="btn btn-primary w-100">{{ __('Confirm') }}</button>
    </form>
</x-layouts::guest>
