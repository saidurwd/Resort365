<x-layouts::app :title="__('Vendor payments')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Vendor payments') => null]">
    <x-slot:actions>
        @if ($canCreate)<a href="{{ route('procurement.payments.create') }}" class="btn btn-primary" data-new-payment><i class="bi bi-plus-lg"></i> {{ __('Pay a vendor') }}</a>@endif
    </x-slot:actions>
    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-payments>
                <thead><tr><th class="ps-3">{{ __('Payment') }}</th><th>{{ __('Vendor') }}</th><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Advance left') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr data-payment="{{ $payment->payment_no }}">
                            <td class="ps-3">{{ $payment->payment_no }}</td><td>{{ $payment->vendor->name }}</td><td>{{ $payment->paid_on->format('d M Y') }}</td><td><x-status-badge :status="$payment->method" /></td>
                            <td class="text-end font-monospace">{{ number_format((float) $payment->amount, 2) }}</td><td class="text-end font-monospace">{{ number_format((float) bcsub((string) $payment->amount, (string) $payment->allocated_amount, 2), 2) }}</td>
                            <td class="text-end pe-3"><a href="{{ route('procurement.payments.show', $payment) }}" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state :title="__('No vendor payments yet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts::app>
