@php($tenant = app(\App\Support\Tenancy\TenantContext::class)->tenantOrFail())

<x-layouts::guest :title="__('Accept invitation')">
    <p class="login-box-msg">{{ __('Join :name. Choose a password to finish setting up your account.', ['name' => $tenant->name]) }}</p>

    {{-- Posts back to the signed URL, so the signature is checked again. --}}
    <form method="POST" action="{{ request()->fullUrl() }}">
        @csrf
        <x-form.input name="email_display" type="email" :label="__('Email')" :value="$user->email" disabled />
        <x-form.input name="name" :label="__('Your name')" :value="$user->name" required autocomplete="name" />
        <x-form.input name="password" type="password" :label="__('Password')" required autocomplete="new-password" :help="__('At least 10 characters, with upper- and lowercase letters and a number.')" />
        <x-form.input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" />
        <button type="submit" class="btn btn-primary w-100">{{ __('Create my account') }}</button>
    </form>
</x-layouts::guest>
