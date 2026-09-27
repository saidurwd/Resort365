@php($title = $role ? __('Edit role') : __('New role'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Roles & permissions') => route('iam.roles.index'), $title => null]">
    <form method="POST" action="{{ $role ? route('iam.roles.update', $role) : route('iam.roles.store') }}">
        @csrf
        @if ($role)
            @method('PUT')
        @endif

        <x-card>
            <div class="row">
                <div class="col-md-6"><x-form.input name="name" :label="__('Name')" :value="$role?->name" required /></div>
                <div class="col-md-6"><x-form.input name="description" :label="__('Description')" :value="$role?->description" /></div>
            </div>
        </x-card>

        <x-card :title="__('Permissions')" icon="bi-shield-check">
            @include('iam::roles.partials.permissions', ['editable' => true])
            <x-slot:footer>
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('iam.roles.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save role') }}</button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
