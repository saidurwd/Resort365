<x-layouts::app :title="$role->label()" :breadcrumbs="[__('Roles & permissions') => route('iam.roles.index'), $role->label() => null]">
    <x-card>
        <p class="mb-1">{{ $role->description }}</p>
        @if ($role->is_system)
            <p class="small text-body-secondary mb-0">{{ __('This is a default role: its permissions are managed by Resort365. Create a custom role for a different set.') }}</p>
        @endif
    </x-card>

    <x-card :title="__('Permissions')" icon="bi-shield-check">
        @include('iam::roles.partials.permissions', ['editable' => false])
    </x-card>
</x-layouts::app>
