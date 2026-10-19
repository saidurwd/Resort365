<x-layouts::app :title="__('Salary components')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Payroll') => null, __('Salary components') => null]">
    @if ($canManage)
        <x-slot:actions><a href="{{ route('payroll.components.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New component') }}</a></x-slot:actions>
    @endif
    <x-card body-class="p-0">
        <table class="table table-sm align-middle mb-0" data-components>
            <thead><tr><th class="ps-3">{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Taxable') }}</th><th>{{ __('Paid') }}</th><th>{{ __('Status') }}</th>@if ($canManage)<th></th>@endif</tr></thead>
            <tbody>
                @forelse ($records as $record)
                    <tr data-component="{{ $record->code }}">
                        <td class="ps-3 font-monospace">{{ $record->code }}@if ($record->is_system) <i class="bi bi-lock text-body-secondary" title="{{ __('System component') }}"></i>@endif</td>
                        <td>{{ $record->name }}</td>
                        <td><x-status-badge :status="$record->kind" /></td>
                        <td>{{ $record->kind === \Modules\Payroll\Enums\ComponentKind::Earning ? ($record->is_taxable ? __('Yes') : __('No')) : '—' }}</td>
                        <td>{{ $record->frequency === \Modules\Payroll\Enums\ComponentFrequency::Annual ? trans_choice(':count time a year|:count times a year', $record->annual_times) : __('Every month') }}</td>
                        <td><span @class(['badge', 'text-bg-success' => $record->is_active, 'text-bg-secondary' => ! $record->is_active])>{{ $record->is_active ? __('Active') : __('Inactive') }}</span></td>
                        @if ($canManage)
                            <td class="text-end pe-3 text-nowrap">
                                <a href="{{ route('payroll.components.edit', $record) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                @unless ($record->is_system)<x-confirm-delete :action="route('payroll.components.destroy', $record)" :text="__('":name" will be removed.', ['name' => $record->name])" />@endunless
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state :title="__('No components yet')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts::app>
