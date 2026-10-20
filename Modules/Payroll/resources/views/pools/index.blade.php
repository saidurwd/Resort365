<x-layouts::app :title="__('Service charge and tips')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Payroll') => null, __('Service charge and tips') => null]">
    <x-slot:actions>
        <a href="{{ route('payroll.pools.participants') }}" class="btn btn-sm btn-outline-secondary" data-participants><i class="bi bi-people"></i> {{ __('Who shares') }}</a>
        @if ($canManage)
            <a href="{{ route('payroll.pools.create') }}" class="btn btn-sm btn-primary" data-new-pool><i class="bi bi-plus-lg"></i> {{ __('Work out a pool') }}</a>
        @endif
    </x-slot:actions>
    <x-card body-class="p-0">
        <table class="table table-sm table-hover align-middle mb-0" data-pools>
            <thead><tr><th class="ps-3">{{ __('Month') }}</th><th>{{ __('Pool') }}</th><th>{{ __('Rule') }}</th><th class="text-end">{{ __('Collected') }}</th><th class="text-end">{{ __('To staff') }}</th><th class="text-end">{{ __('People') }}</th><th class="pe-3">{{ __('Status') }}</th></tr></thead>
            <tbody>
                @forelse ($pools as $pool)
                    <tr data-pool="{{ $pool->period->format('Y-m') }}-{{ $pool->kind->value }}">
                        <td class="ps-3"><a href="{{ route('payroll.pools.show', $pool) }}">{{ $pool->period->format('F Y') }}</a></td>
                        <td><x-status-badge :status="$pool->kind" /></td>
                        <td>{{ $pool->rule->label() }}</td>
                        <td class="text-end font-monospace">{{ number_format((float) $pool->collected, 2) }}</td>
                        <td class="text-end font-monospace">{{ number_format((float) $pool->distributable, 2) }}</td>
                        <td class="text-end">{{ $pool->employees_count }}</td>
                        <td class="pe-3"><x-status-badge :status="$pool->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state :title="__('No pools yet')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts::app>
