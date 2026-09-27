{{-- TODO(step-7.3): the platform console (tenants, plans, subscriptions). --}}
<x-layouts::guest :title="__('Platform console')">
    <div class="text-center">
        <i class="bi bi-shield-lock text-primary display-5"></i>
        <h1 class="h4 mt-3">{{ __('Platform console') }}</h1>
        <p class="text-body-secondary">{{ __('Signed in as :name (:email).', ['name' => $admin->name, 'email' => $admin->email]) }}</p>

        <form method="POST" action="{{ route('platform.logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-right"></i> {{ __('Sign out') }}</button>
        </form>
    </div>
</x-layouts::guest>
