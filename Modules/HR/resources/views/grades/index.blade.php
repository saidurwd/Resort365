<x-layouts::app :title="__('Grades')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Setup') => null, __('Grades') => null]">
    @if ($canManage)
        <x-slot:actions><a href="{{ route('hr.grades.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New grade') }}</a></x-slot:actions>
    @endif
    <x-card body-class="p-0">
        <table class="table table-sm align-middle mb-0" data-grades>
            <thead><tr><th class="ps-3">{{ __('Code') }}</th><th>{{ __('Name') }}</th><th class="text-end">{{ __('Level') }}</th><th class="text-end">{{ __('Employees') }}</th><th>{{ __('Status') }}</th>@if ($canManage)<th></th>@endif</tr></thead>
            <tbody>
                @forelse ($records as $record)
                    <tr data-grade="{{ $record->code }}">
                        <td class="ps-3 font-monospace">{{ $record->code }}</td>
                        <td>{{ $record->name }}</td>
                        <td class="text-end">{{ $record->level }}</td>
                        <td class="text-end">{{ $record->employees_count }}</td>
                        <td><span @class(['badge', 'text-bg-success' => $record->is_active, 'text-bg-secondary' => ! $record->is_active])>{{ $record->is_active ? __('Active') : __('Inactive') }}</span></td>
                        @if ($canManage)
                            <td class="text-end pe-3 text-nowrap">
                                <a href="{{ route('hr.grades.edit', $record) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                <x-confirm-delete :action="route('hr.grades.destroy', $record)" :text="__('":name" will be removed.', ['name' => $record->name])" />
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state :title="__('No grades yet')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts::app>
