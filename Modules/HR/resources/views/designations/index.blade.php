<x-layouts::app :title="__('Designations')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Setup') => null, __('Designations') => null]">
    @if ($canManage)
        <x-slot:actions><a href="{{ route('hr.designations.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New designation') }}</a></x-slot:actions>
    @endif
    <x-card body-class="p-0">
        <table class="table table-sm align-middle mb-0" data-designations>
            <thead><tr><th class="ps-3">{{ __('Designation') }}</th><th>{{ __('Department') }}</th><th class="text-end">{{ __('Employees') }}</th><th>{{ __('Status') }}</th>@if ($canManage)<th></th>@endif</tr></thead>
            <tbody>
                @forelse ($records as $record)
                    <tr data-designation="{{ $record->name }}">
                        <td class="ps-3">{{ $record->name }}</td>
                        <td>{{ $departments[$record->department_id] ?? '—' }}</td>
                        <td class="text-end">{{ $record->employees_count }}</td>
                        <td><span @class(['badge', 'text-bg-success' => $record->is_active, 'text-bg-secondary' => ! $record->is_active])>{{ $record->is_active ? __('Active') : __('Inactive') }}</span></td>
                        @if ($canManage)
                            <td class="text-end pe-3 text-nowrap">
                                <a href="{{ route('hr.designations.edit', $record) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                <x-confirm-delete :action="route('hr.designations.destroy', $record)" :text="__('":name" will be removed.', ['name' => $record->name])" />
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state :title="__('No designations yet')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts::app>
