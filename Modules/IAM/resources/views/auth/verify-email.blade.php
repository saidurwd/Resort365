<x-layouts::guest :title="__('Verify your email')">
    <p>{{ __('Please confirm your email address by following the link we emailed you. Didn\'t get it? We can send another.') }}</p>

    <div class="d-flex justify-content-between gap-2">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary">{{ __('Resend verification email') }}</button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">{{ __('Sign out') }}</button>
        </form>
    </div>
</x-layouts::guest>
