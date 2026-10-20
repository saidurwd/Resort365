<x-layouts::app :title="__('Payroll runs')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Payroll') => null, __('Payroll runs') => null]">
    @if ($canCreate)
        <x-slot:actions><a href="{{ route('payroll.runs.create') }}" class="btn btn-sm btn-primary" data-new-run><i class="bi bi-plus-lg"></i> {{ __('New payroll run') }}</a></x-slot:actions>
    @endif
    <x-card body-class="p-0">
        <table class="table table-sm table-hover align-middle mb-0" data-runs>
            <thead><tr><th class="ps-3">{{ __('Month') }}</th><th>{{ __('Run') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Employees') }}</th><th class="text-end">{{ __('Gross') }}</th><th class="text-end">{{ __('Income tax') }}</th><th class="text-end pe-3">{{ __('Net pay') }}</th></tr></thead>
            <tbody>
                @forelse ($runs as $run)
                    <tr data-run="{{ $run->period->format('Y-m') }}">
                        <td class="ps-3"><a href="{{ route('payroll.runs.show', $run) }}">{{ $run->period->format('F Y') }}</a></td>
                        <td class="font-monospace">{{ $run->run_no }}</td>
                        <td><x-status-badge :status="$run->status" /></td>
                        <td class="text-end">{{ $run->employees_count }}</td>
                        <td class="text-end font-monospace">{{ number_format((float) $run->gross_total, 2) }}</td>
                        <td class="text-end font-monospace">{{ number_format((float) $run->tax_total, 2) }}</td>
                        <td class="text-end font-monospace pe-3">{{ number_format((float) $run->net_total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state :title="__('No payroll runs yet')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts::app>
