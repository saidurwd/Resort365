<x-layouts::app :title="$payment->payment_no" :subtitle="$payment->vendor->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Vendor payments') => route('procurement.payments.index'), $payment->payment_no => null]">
    <div class="row">
        <div class="col-xl-8">
            <x-card :title="__('Applied to')" icon="bi-receipt" body-class="p-0">
                <table class="table table-sm align-middle mb-0" data-allocations>
                    <tbody>
                        @forelse ($payment->allocations as $allocation)
                            <tr><td class="ps-3"><a href="{{ route('procurement.bills.show', $allocation->bill) }}">{{ $allocation->bill->bill_no }}</a></td><td class="text-end font-monospace pe-3">{{ number_format((float) $allocation->amount, 2) }}</td></tr>
                        @empty
                            <tr><td class="ps-3 text-body-secondary">{{ __('Not applied to any bill: an advance.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
            @if ($canAllocate)
                <x-card :title="__('Apply the rest')" icon="bi-box-arrow-in-right">
                    <form method="POST" action="{{ route('procurement.payments.allocate', $payment) }}" data-allocate-form>
                        @csrf
                        <table class="table table-sm align-middle">
                            <tbody>
                                @forelse ($bills as $open)
                                    <tr data-bill="{{ $open->bill_no }}"><td>{{ $open->bill_no }}</td><td class="text-end font-monospace">{{ number_format((float) bcsub((string) $open->total, (string) $open->paid_amount, 2), 2) }}</td>
                                        <td style="width:10rem"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="allocations[{{ $open->id }}]" aria-label="{{ __('Apply') }}"></td></tr>
                                @empty
                                    <tr><td class="text-body-secondary">{{ __('No approved bills are waiting for payment.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <button type="submit" class="btn btn-outline-primary" data-allocate>{{ __('Apply') }}</button>
                    </form>
                </x-card>
            @endif
        </div>
        <div class="col-xl-4">
            <x-card :title="__('Payment')" icon="bi-cash-coin">
                <dl class="row mb-0" data-payment-summary>
                    <dt class="col-5">{{ __('Vendor') }}</dt><dd class="col-7"><a href="{{ route('procurement.vendors.show', $payment->vendor_id) }}">{{ $payment->vendor->name }}</a></dd>
                    <dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ $payment->paid_on->format('d M Y') }}</dd>
                    <dt class="col-5">{{ __('Method') }}</dt><dd class="col-7"><x-status-badge :status="$payment->method" /></dd>
                    <dt class="col-5">{{ __('Amount') }}</dt><dd class="col-7 font-monospace">{{ number_format((float) $payment->amount, 2) }}</dd>
                    <dt class="col-5">{{ __('Applied') }}</dt><dd class="col-7 font-monospace">{{ number_format((float) $payment->allocated_amount, 2) }}</dd>
                    <dt class="col-5">{{ __('Advance left') }}</dt><dd class="col-7 font-monospace" data-advance>{{ number_format((float) bcsub((string) $payment->amount, (string) $payment->allocated_amount, 2), 2) }}</dd>
                    @if ($payment->reference)<dt class="col-5">{{ __('Reference') }}</dt><dd class="col-7">{{ $payment->reference }}</dd>@endif
                    @if ($payment->notes)<dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7">{{ $payment->notes }}</dd>@endif
                </dl>
            </x-card>
            <x-audit-trail :entries="$trail" />
        </div>
    </div>
</x-layouts::app>
