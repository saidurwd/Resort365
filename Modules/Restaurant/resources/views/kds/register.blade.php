<x-layouts::kds :title="__('Register this kitchen display')">
    <div class="pos-card mx-auto" data-register-kds>
        <h1 class="h3"><i class="bi bi-display"></i> {{ __('Register this kitchen display') }}</h1>
        <p class="text-body-secondary">{{ __('Enter the display token of the station this screen serves. A manager finds it under Restaurant → Outlets → the outlet → Stations ("Display token").') }}</p>
        <form method="POST" action="{{ route('kds.register.store') }}">
            @csrf
            <x-form.input name="token" :label="__('Display token')" autocomplete="off" class="form-control-lg font-monospace text-uppercase" required />
            <button type="submit" class="btn btn-primary btn-lg pos-btn w-100">{{ __('Register') }}</button>
        </form>
        <p class="small text-body-secondary mt-3 mb-0">{{ __('Signed in to Resort365 as a chef or manager? Open the kitchen display from the menu instead.') }}</p>
    </div>
</x-layouts::kds>
