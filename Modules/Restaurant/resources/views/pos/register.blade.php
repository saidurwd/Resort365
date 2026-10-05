<x-layouts::pos :title="__('Register this device')">
    <div class="pos-card mx-auto" data-register-device>
        <h1 class="h3"><i class="bi bi-tablet"></i> {{ __('Register this device') }}</h1>
        <p class="text-body-secondary">{{ __('Enter the device token of this terminal. A manager finds it under Restaurant → Outlets → the outlet → Terminals ("New token").') }}</p>
        <form method="POST" action="{{ route('pos.register.store') }}">
            @csrf
            <x-form.input name="token" :label="__('Device token')" autocomplete="off" class="form-control-lg font-monospace text-uppercase" required />
            <button type="submit" class="btn btn-primary btn-lg pos-btn w-100">{{ __('Register') }}</button>
        </form>
    </div>
</x-layouts::pos>
