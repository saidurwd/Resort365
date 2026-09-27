<x-layouts::app :title="__('Roles & permissions')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Roles & permissions') => null]">
    @can('create', \Modules\IAM\Models\Role::class)
        <x-slot:actions>
            <a href="{{ route('iam.roles.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New role') }}</a>
        </x-slot:actions>
    @endcan

    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">{{ __('Role') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th class="text-end">{{ __('Permissions') }}</th>
                        <th class="text-end">{{ __('Users') }}</th>
                        <th class="text-end pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold">{{ $role->label() }}</div>
                                <div class="small text-body-secondary">{{ $role->description }}</div>
                            </td>
                            <td>
                                @if ($role->is_system)
                                    <span class="badge text-bg-secondary">{{ __('Default') }}</span>
                                @else
                                    <span class="badge text-bg-info">{{ __('Custom') }}</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">{{ $role->permissions_count }} / {{ $permissionCount }}</td>
                            <td class="text-end">{{ $role->users_count }}</td>
                            <td class="text-end text-nowrap pe-3">
                                @can('update', $role)
                                    <a href="{{ route('iam.roles.edit', $role) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                @else
                                    <a href="{{ route('iam.roles.show', $role) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> {{ __('View') }}</a>
                                @endcan
                                @can('delete', $role)
                                    <x-confirm-delete :action="route('iam.roles.destroy', $role)" icon-only :title="__('Delete role :name?', ['name' => $role->name])" />
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts::app>
