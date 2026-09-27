<x-card :title="__('Change password')" icon="bi-key">
    <form method="POST" action="{{ route('user-password.update') }}">
        @csrf
        @method('PUT')
        <x-form.input name="current_password" type="password" :label="__('Current password')" required autocomplete="current-password" error-bag="updatePassword" />
        <x-form.input name="password" type="password" :label="__('New password')" required autocomplete="new-password" error-bag="updatePassword"
                      :help="__('At least 10 characters, with upper- and lowercase letters and a number.')" />
        <x-form.input name="password_confirmation" type="password" :label="__('Confirm new password')" required autocomplete="new-password" error-bag="updatePassword" />
        <button type="submit" class="btn btn-primary">{{ __('Change password') }}</button>
    </form>
</x-card>
