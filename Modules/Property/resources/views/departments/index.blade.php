<x-layouts::app :title="__('Departments')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Departments') => null]">
    @can('create', \Modules\Property\Models\Department::class)
        <x-slot:actions>
            <a href="{{ route('property.departments.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New department') }}</a>
        </x-slot:actions>
    @endcan

    <x-card body-class="p-0">
        @if ($departments->isEmpty())
            <x-empty-state icon="bi-diagram-3" :title="__('No departments yet')" :message="__('Departments are shared by all your properties and used as cost centres.')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-departments>
                    <thead>
                        <tr>
                            <th class="ps-3">{{ __('Code') }}</th>
                            <th>{{ __('Department') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($departments as $department)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $department->code }}</td>
                                <td>{{ $department->name }}</td>
                                <td class="text-body-secondary">{{ $department->description }}</td>
                                <td>@include('property::partials.active-badge', ['active' => $department->is_active])</td>
                                <td class="text-end pe-3 text-nowrap">
                                    @can('update', $department)
                                        <a href="{{ route('property.departments.edit', $department) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                    @endcan
                                    @can('delete', $department)
                                        <x-confirm-delete :action="route('property.departments.destroy', $department)" icon-only :title="__('Delete department :name?', ['name' => $department->name])" />
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts::app>
