<x-layouts::guest :title="__('Platform sign in')">
    <p class="login-box-msg">{{ __('Platform administration') }}</p>

    <form method="POST" action="{{ route('platform.login.store') }}">
        @csrf
        <x-form.input name="email" type="email" :label="__('Email')" required autofocus autocomplete="username" />
        <x-form.input name="password" type="password" :label="__('Password')" required autocomplete="current-password" />

        <div class="d-flex justify-content-between align-items-center">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                <label class="form-check-label" for="remember">{{ __('Remember me') }}</label>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('Sign in') }}</button>
        </div>
    </form>
</x-layouts::guest>
