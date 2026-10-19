<x-layouts::app :title="__('Salary structures')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Payroll') => null, __('Salary structures') => null]">
    @if ($canManage)
        <x-slot:actions><a href="{{ route('payroll.structures.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New structure') }}</a></x-slot:actions>
    @endif
    <x-card body-class="p-0">
        <table class="table table-sm align-middle mb-0" data-structures>
            <thead><tr><th class="ps-3">{{ __('Code') }}</th><th>{{ __('Name') }}</th><th class="text-end">{{ __('Components') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($records as $record)
                    <tr data-structure="{{ $record->code }}">
                        <td class="ps-3 font-monospace">{{ $record->code }}</td>
                        <td><a href="{{ route('payroll.structures.show', $record) }}">{{ $record->name }}</a><div class="small text-body-secondary">{{ $record->description }}</div></td>
                        <td class="text-end">{{ $record->lines_count }}</td>
                        <td><span @class(['badge', 'text-bg-success' => $record->is_active, 'text-bg-secondary' => ! $record->is_active])>{{ $record->is_active ? __('Active') : __('Inactive') }}</span></td>
                        <td class="text-end pe-3 text-nowrap">
                            <a href="{{ route('payroll.structures.show', $record) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> {{ __('Open') }}</a>
                            @if ($canManage)
                                <a href="{{ route('payroll.structures.edit', $record) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                <x-confirm-delete :action="route('payroll.structures.destroy', $record)" :text="__('":name" will be removed.', ['name' => $record->name])" />
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state :title="__('No salary structures yet')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts::app>
