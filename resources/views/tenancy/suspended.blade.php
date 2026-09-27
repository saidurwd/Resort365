<x-layouts::guest :title="__('Account suspended')">
    <div class="text-center">
        <i class="bi bi-pause-circle text-warning display-5"></i>
        <h1 class="h4 mt-3">{{ __('This account is suspended') }}</h1>
        <p class="text-body-secondary mb-0">
            {{ __(':name cannot be used right now. Please contact your administrator or Resort365 support.', ['name' => $tenant->name]) }}
        </p>
    </div>
</x-layouts::guest>
