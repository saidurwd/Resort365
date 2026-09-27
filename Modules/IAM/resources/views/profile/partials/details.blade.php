<x-card :title="__('Your details')" icon="bi-person">
    <form method="POST" action="{{ route('user-profile-information.update') }}">
        @csrf
        @method('PUT')
        <x-form.input name="name" :label="__('Name')" :value="$user->name" required autocomplete="name" error-bag="updateProfileInformation" />
        <x-form.input name="email" type="email" :label="__('Email')" :value="$user->email" required autocomplete="username" error-bag="updateProfileInformation"
                      :help="__('Changing your email means verifying the new address.')" />
        <button type="submit" class="btn btn-primary">{{ __('Save details') }}</button>
    </form>
</x-card>
