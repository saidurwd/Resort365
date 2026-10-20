<x-layouts::app :title="$pool->kind->label().' · '.$pool->period->format('F Y')" :subtitle="$subtitle" :breadcrumbs="[__('Payroll') => null, __('Service charge and tips') => route('payroll.pools.index'), $pool->period->format('F Y') => null]">
    @if ($canManage)
        <x-slot:actions>
            @if ($pool->status === \Modules\Payroll\Enums\PoolStatus::Calculated)
                <form method="POST" action="{{ route('payroll.pools.recalculate', $pool) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary" data-recalculate><i class="bi bi-arrow-repeat"></i> {{ __('Work out again') }}</button></form>
                <form method="POST" action="{{ route('payroll.pools.finalise', $pool) }}" class="d-inline" data-confirm="{{ __('Send to payroll?') }}" data-confirm-text="{{ __('The payroll run for this month adds these shares when it is calculated.') }}">@csrf<button class="btn btn-sm btn-primary" data-finalise><i class="bi bi-send"></i> {{ __('Send to payroll') }}</button></form>
                <form method="POST" action="{{ route('payroll.pools.destroy', $pool) }}" class="d-inline" data-confirm="{{ __('Delete this pool?') }}" data-confirm-variant="danger">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" data-delete><i class="bi bi-trash"></i></button></form>
            @elseif ($pool->status === \Modules\Payroll\Enums\PoolStatus::Finalised)
                <form method="POST" action="{{ route('payroll.pools.reopen', $pool) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary" data-reopen>{{ __('Take back from payroll') }}</button></form>
            @endif
        </x-slot:actions>
    @endif

    <div class="row g-2 mb-3" data-pool-summary>
        <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="small text-body-secondary">{{ __('Collected') }}</div><div class="fs-5 fw-semibold font-monospace" data-figure="collected">{{ number_format((float) $pool->collected, 2) }}</div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="small text-body-secondary">{{ __('Share for staff') }} ({{ rtrim(rtrim((string) $pool->staff_percent, '0'), '.') }}%)</div><div class="fs-5 fw-semibold font-monospace" data-figure="distributable">{{ number_format((float) $pool->distributable, 2) }}</div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="small text-body-secondary">{{ __('People') }}</div><div class="fs-5 fw-semibold" data-figure="people">{{ $pool->employees_count }}</div></div></div>
        <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="small text-body-secondary">{{ __('Status') }}</div><div class="fs-5"><x-status-badge :status="$pool->status" /></div></div></div>
    </div>

    @if ($waiting)
        <div class="alert alert-warning" data-waiting>{{ __('The shares are not on payslips yet: calculate the payroll run for this month.') }}
            @if ($run) <a href="{{ route('payroll.runs.show', $run) }}">{{ $run->run_no }}</a> @endif</div>
    @endif
    @if ($pool->status === \Modules\Payroll\Enums\PoolStatus::Distributed && $run)
        <div class="alert alert-success" data-distributed>{{ __('Paid through payroll run :no.', ['no' => $run->run_no]) }} <a href="{{ route('payroll.runs.show', $run) }}">{{ __('Open') }}</a></div>
    @endif

    <x-card :title="__('Shares')" icon="bi-people" body-class="p-0">
        <table class="table table-sm table-hover align-middle mb-0" data-allocations>
            <thead><tr><th class="ps-3">{{ __('Employee') }}</th><th class="text-end">{{ $pool->rule === \Modules\Payroll\Enums\PoolRule::DaysWorked ? __('Days') : ($pool->rule === \Modules\Payroll\Enums\PoolRule::Points ? __('Points') : __('Weight')) }}</th><th class="text-end">{{ __('Share') }}</th><th class="pe-3">{{ __('Payslip') }}</th></tr></thead>
            <tbody>
                @forelse ($pool->allocations as $allocation)
                    <tr data-allocation="{{ $allocation->employee_no }}">
                        <td class="ps-3">{{ $allocation->employee_no }} · {{ $allocation->employee_name }}</td>
                        <td class="text-end font-monospace">{{ rtrim(rtrim((string) $allocation->weight, '0'), '.') }}</td>
                        <td class="text-end font-monospace">{{ number_format((float) $allocation->amount, 2) }}</td>
                        <td class="pe-3">@if ($allocation->payslip_id) <a href="{{ route('payroll.payslips.show', $allocation->payslip_id) }}">{{ __('Payslip') }}</a> @else — @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state :title="__('Nobody shares in this pool')" /></td></tr>
                @endforelse
            </tbody>
            @if ($pool->allocations->isNotEmpty())
                <tfoot><tr class="fw-semibold"><td class="ps-3">{{ __('Total') }}</td><td></td><td class="text-end font-monospace" data-figure="allocated">{{ number_format((float) $pool->allocations->sum('amount'), 2) }}</td><td></td></tr></tfoot>
            @endif
        </table>
    </x-card>
    <x-audit-trail :entries="$trail" />
</x-layouts::app>
