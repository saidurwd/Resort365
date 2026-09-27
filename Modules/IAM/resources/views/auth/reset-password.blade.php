<x-layouts::guest :title="__('Choose a new password')">
    <p class="login-box-msg">{{ __('Choose a new password.') }}</p>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-form.input name="email" type="email" :label="__('Email')" :value="$request->query('email')" required autocomplete="username" />
        <x-form.input name="password" type="password" :label="__('New password')" required autocomplete="new-password" :help="__('At least 10 characters, with upper- and lowercase letters and a number.')" />
        <x-form.input name="password_confirmation" type="password" :label="__('Confirm new password')" required autocomplete="new-password" />
        <button type="submit" class="btn btn-primary w-100">{{ __('Reset password') }}</button>
    </form>
</x-layouts::guest>
