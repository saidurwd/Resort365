@php($money = fn (mixed $value): string => number_format((float) $value, 2))

<x-layouts::app :title="$loan->loan_no" :subtitle="$employee?->name" :breadcrumbs="[__('Payroll') => null, __('Loans and advances') => route('payroll.loans.index'), $loan->loan_no => null]">
    <div class="row">
        <div class="col-xl-7">
            <x-card :title="__('Installments')" icon="bi-calendar-check" body-class="p-0">
                <table class="table table-sm align-middle mb-0" data-installments>
                    <thead><tr><th class="ps-3">#</th><th>{{ __('Month') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th>@if ($canManage)<th></th>@endif</tr></thead>
                    <tbody>
                        @foreach ($loan->installments as $installment)
                            <tr data-installment="{{ $installment->sequence }}">
                                <td class="ps-3">{{ $installment->sequence }}</td>
                                <td>{{ $installment->due_month->format('F Y') }}</td>
                                <td class="text-end font-monospace">{{ $money($installment->amount) }}</td>
                                <td><x-status-badge :status="$installment->status" />@if ($installment->note) <span class="small text-body-secondary">{{ $installment->note }}</span>@endif</td>
                                @if ($canManage)
                                    <td class="text-end pe-3 text-nowrap">
                                        @if ($installment->status === \Modules\Payroll\Enums\InstallmentStatus::Scheduled)
                                            <form method="POST" action="{{ route('payroll.loans.skip', [$loan, $installment]) }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-secondary" data-skip>{{ __('Move to the end') }}</button></form>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#waive-{{ $installment->id }}" data-waive-open>{{ __('Write off') }}</button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                            @if ($canManage && $installment->status === \Modules\Payroll\Enums\InstallmentStatus::Scheduled)
                                <tr class="collapse" id="waive-{{ $installment->id }}"><td colspan="5" class="ps-3">
                                    <form method="POST" action="{{ route('payroll.loans.waive', [$loan, $installment]) }}" class="d-flex gap-2" data-waive-form>@csrf
                                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="{{ __('Why is it written off?') }}" required>
                                        <button type="submit" class="btn btn-sm btn-danger">{{ __('Write off') }}</button>
                                    </form>
                                </td></tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        </div>
        <div class="col-xl-5">
            <x-card :title="__('Loan')" icon="bi-wallet2">
                <dl class="row mb-0" data-loan-summary>
                    <dt class="col-5">{{ __('Employee') }}</dt><dd class="col-7">{{ $employee?->name ?? '—' }}</dd>
                    <dt class="col-5">{{ __('Kind') }}</dt><dd class="col-7"><x-status-badge :status="$loan->kind" /></dd>
                    <dt class="col-5">{{ __('Amount') }}</dt><dd class="col-7 font-monospace">{{ $money($loan->principal) }}</dd>
                    <dt class="col-5">{{ __('Paid on') }}</dt><dd class="col-7">{{ $loan->disbursed_on->format('d M Y') }} · {{ mb_strtolower($loan->payment_method->label()) }}</dd>
                    <dt class="col-5">{{ __('Status') }}</dt><dd class="col-7"><x-status-badge :status="$loan->status" /></dd>
                    @if ($loan->reason)<dt class="col-5">{{ __('Reason') }}</dt><dd class="col-7">{{ $loan->reason }}</dd>@endif
                </dl>
            </x-card>
            <x-audit-trail :entries="$trail" />
        </div>
    </div>
</x-layouts::app>
