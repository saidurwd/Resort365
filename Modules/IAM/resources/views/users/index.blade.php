<x-layouts::app :title="__('Users')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Users') => null]">
    <x-slot:actions>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#invite-user">
            <i class="bi bi-person-plus"></i> {{ __('Invite user') }}
        </button>
    </x-slot:actions>

    <x-card body-class="p-3">
        <x-datatable id="users-table" :url="route('iam.users.data')" :columns="$columns" :order="[[0, 'asc']]" :empty-text="__('No users yet.')" />
    </x-card>

    <x-modal id="invite-user" :title="__('Invite a user')" :show="$errors->hasAny(['name', 'email'])">
        <form method="POST" action="{{ route('iam.users.store') }}" id="invite-user-form">
            @csrf
            <x-form.input name="name" :label="__('Name')" required autocomplete="off" />
            <x-form.input name="email" type="email" :label="__('Email')" required autocomplete="off" :help="__('We will email a link to set a password. It expires in 7 days.')" />
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" form="invite-user-form" class="btn btn-primary"><i class="bi bi-send"></i> {{ __('Send invitation') }}</button>
        </x-slot:footer>
    </x-modal>
</x-layouts::app>
