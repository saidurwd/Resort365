<x-layouts::app :title="__('Leave types')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Setup') => null, __('Leave types') => null]">
    @if ($canManage)
        <x-slot:actions><a href="{{ route('hr.leave-types.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New leave type') }}</a></x-slot:actions>
    @endif
    <x-card body-class="p-0">
        <table class="table table-sm align-middle mb-0" data-leave-types>
            <thead><tr><th class="ps-3">{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Pay') }}</th><th class="text-end">{{ __('Days a year') }}</th><th>{{ __('Accrual') }}</th><th class="text-end">{{ __('Carry forward') }}</th><th class="text-end">{{ __('Service needed') }}</th><th>{{ __('Status') }}</th>@if ($canManage)<th></th>@endif</tr></thead>
            <tbody>
                @forelse ($records as $record)
                    <tr data-leave-type="{{ $record->code }}">
                        <td class="ps-3"><span class="badge text-bg-{{ $record->color }}">{{ $record->code }}</span></td>
                        <td>{{ $record->name }}@if ($record->gender) <span class="small text-body-secondary">({{ $record->gender->label() }})</span>@endif</td>
                        <td>{{ $record->is_paid ? __('Paid') : __('Unpaid') }}</td>
                        <td class="text-end">{{ $record->has_balance ? rtrim(rtrim((string) ($record->policy->annual_days ?? 0), '0'), '.') : '—' }}</td>
                        <td>{{ $record->has_balance ? ($record->policy?->accrual->label() ?? '—') : '—' }}</td>
                        <td class="text-end">{{ $record->has_balance ? rtrim(rtrim((string) ($record->policy->carry_forward_max ?? 0), '0'), '.') ?: '0' : '—' }}</td>
                        <td class="text-end">{{ $record->policy?->min_service_months ? trans_choice(':count month|:count months', $record->policy->min_service_months) : '—' }}</td>
                        <td><span @class(['badge', 'text-bg-success' => $record->is_active, 'text-bg-secondary' => ! $record->is_active])>{{ $record->is_active ? __('Active') : __('Inactive') }}</span></td>
                        @if ($canManage)
                            <td class="text-end pe-3 text-nowrap">
                                <a href="{{ route('hr.leave-types.edit', $record) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                <x-confirm-delete :action="route('hr.leave-types.destroy', $record)" :text="__('":name" will be removed.', ['name' => $record->name])" />
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="9"><x-empty-state :title="__('No leave types yet')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts::app>
