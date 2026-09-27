<x-layouts::guest :title="__('Forgot password')">
    <p class="login-box-msg">{{ __('Enter your email and we will send you a link to choose a new password.') }}</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-form.input name="email" type="email" :label="__('Email')" required autofocus autocomplete="username" />
        <button type="submit" class="btn btn-primary w-100">{{ __('Email reset link') }}</button>
    </form>

    <p class="mt-3 mb-0 text-center small"><a href="{{ route('login') }}">{{ __('Back to sign in') }}</a></p>
</x-layouts::guest>
