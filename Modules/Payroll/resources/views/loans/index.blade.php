<x-layouts::app :title="__('Loans and advances')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Payroll') => null, __('Loans and advances') => null]">
    @if ($canManage)
        <x-slot:actions><a href="{{ route('payroll.loans.create') }}" class="btn btn-sm btn-primary" data-new-loan><i class="bi bi-plus-lg"></i> {{ __('New loan or advance') }}</a></x-slot:actions>
    @endif
    <x-card body-class="p-0">
        <table class="table table-sm table-hover align-middle mb-0" data-loans>
            <thead><tr><th class="ps-3">{{ __('Loan') }}</th><th>{{ __('Employee') }}</th><th>{{ __('Kind') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Left to deduct') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
                @forelse ($loans as $loan)
                    <tr data-loan="{{ $loan->loan_no }}">
                        <td class="ps-3"><a href="{{ route('payroll.loans.show', $loan) }}">{{ $loan->loan_no }}</a></td>
                        <td>{{ $names[$loan->employee_id] ?? '#'.$loan->employee_id }}</td>
                        <td><x-status-badge :status="$loan->kind" /></td>
                        <td class="text-end font-monospace">{{ number_format((float) $loan->principal, 2) }}</td>
                        <td class="text-end font-monospace">{{ number_format((float) $loan->installments->where('status', \Modules\Payroll\Enums\InstallmentStatus::Scheduled)->sum('amount'), 2) }}</td>
                        <td><x-status-badge :status="$loan->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state :title="__('No loans or advances')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts::app>
