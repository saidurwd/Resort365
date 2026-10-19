<x-layouts::app :title="__('Shifts')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Setup') => null, __('Shifts') => null]">
    @if ($canManage)
        <x-slot:actions><a href="{{ route('hr.shifts.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New shift') }}</a></x-slot:actions>
    @endif
    <x-card body-class="p-0">
        <table class="table table-sm align-middle mb-0" data-shifts>
            <thead><tr><th class="ps-3">{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Hours') }}</th><th class="text-end">{{ __('Break (min)') }}</th><th class="text-end">{{ __('Grace (min)') }}</th><th class="text-end">{{ __('Overtime after (min)') }}</th><th>{{ __('Status') }}</th>@if ($canManage)<th></th>@endif</tr></thead>
            <tbody>
                @forelse ($records as $record)
                    <tr data-shift="{{ $record->code }}">
                        <td class="ps-3"><span class="badge text-bg-{{ $record->color }}">{{ $record->code }}</span></td>
                        <td>{{ $record->name }}</td>
                        <td class="font-monospace">{{ $record->window() }}@if ($record->crossesMidnight()) <span class="small text-body-secondary">{{ __('(next day)') }}</span>@endif</td>
                        <td class="text-end">{{ $record->break_minutes }}</td>
                        <td class="text-end">{{ $record->grace_minutes }}</td>
                        <td class="text-end">{{ $record->overtime_after_minutes }}</td>
                        <td><span @class(['badge', 'text-bg-success' => $record->is_active, 'text-bg-secondary' => ! $record->is_active])>{{ $record->is_active ? __('Active') : __('Inactive') }}</span></td>
                        @if ($canManage)
                            <td class="text-end pe-3 text-nowrap">
                                <a href="{{ route('hr.shifts.edit', $record) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                <x-confirm-delete :action="route('hr.shifts.destroy', $record)" :text="__('":name" will be removed.', ['name' => $record->name])" />
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty-state :title="__('No shifts yet')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts::app>
