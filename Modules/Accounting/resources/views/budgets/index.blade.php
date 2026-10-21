<x-layouts::app :title="__('Budgets')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Budgets') => null]">
    @if ($canManage)
        <x-slot:actions><a href="{{ route('accounting.budgets.create') }}" class="btn btn-sm btn-primary" data-new-budget><i class="bi bi-plus-lg"></i> {{ __('New budget') }}</a></x-slot:actions>
    @endif
    <x-card body-class="p-0">
        <table class="table table-sm table-hover align-middle mb-0" data-budgets>
            <thead><tr><th class="ps-3">{{ __('Budget') }}</th><th>{{ __('Fiscal year') }}</th><th>{{ __('Property') }}</th><th class="text-end">{{ __('Lines') }}</th><th class="text-end pe-3"></th></tr></thead>
            <tbody>
                @forelse ($budgets as $budget)
                    <tr data-budget="{{ $budget->id }}">
                        <td class="ps-3"><a href="{{ route('accounting.budgets.show', $budget) }}">{{ $budget->name }}</a></td>
                        <td>{{ $budget->fiscalYear->name }}</td>
                        <td>{{ $budget->property_id === null ? __('All properties') : ($properties[$budget->property_id] ?? '#'.$budget->property_id) }}</td>
                        <td class="text-end">{{ $budget->lines_count }}</td>
                        <td class="text-end pe-3"><a href="{{ route('accounting.budgets.report', $budget) }}" class="btn btn-sm btn-outline-primary" data-budget-report>{{ __('Against actual') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state :title="__('No budgets yet')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts::app>
