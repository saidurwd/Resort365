<x-layouts::app :title="__('Pay a vendor')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Vendor payments') => route('procurement.payments.index'), __('New') => null]">
    @if ($vendorId === null)
        <x-card :title="__('Vendor')" icon="bi-shop">
            <form method="GET" class="row g-2 align-items-end" data-vendor-pick>
                <div class="col-md-6"><x-form.select name="vendor" :label="__('Vendor')" :options="$vendors" :placeholder="__('Choose a vendor')" required /></div>
                <div class="col-md-3 mb-3"><button type="submit" class="btn btn-primary">{{ __('Continue') }}</button></div>
            </form>
        </x-card>
    @else
        <form method="POST" action="{{ route('procurement.payments.store') }}" data-payment-form>
            @csrf
            <input type="hidden" name="vendor_id" value="{{ $vendorId }}">
            <x-card :title="$vendors[$vendorId] ?? __('Vendor')" icon="bi-cash-coin">
                <div class="row">
                    <div class="col-md-3"><x-form.select name="method" :label="__('Method')" :options="$methods" :value="old('method', 'bank_transfer')" required /></div>
                    <div class="col-md-3"><x-form.input name="amount" type="number" step="0.01" :label="__('Amount paid')" :value="old('amount')" required /></div>
                    <div class="col-md-3"><x-form.date name="paid_on" :label="__('Paid on')" :value="old('paid_on', now()->toDateString())" /></div>
                    <div class="col-md-3"><x-form.input name="reference" :label="__('Reference or cheque no.')" :value="old('reference')" /></div>
                    <div class="col-12"><x-form.input name="notes" :label="__('Notes')" :value="old('notes')" /></div>
                </div>
                <h2 class="h6 mt-2">{{ __('Apply to bills') }}</h2>
                <p class="small text-body-secondary">{{ __('Whatever you leave unapplied stays with the vendor as an advance.') }}</p>
                <table class="table table-sm align-middle mb-0" data-open-bills>
                    <thead><tr><th>{{ __('Bill') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Balance') }}</th><th class="text-end">{{ __('Apply') }}</th></tr></thead>
                    <tbody>
                        @forelse ($bills as $open)
                            @php($balance = bcsub((string) $open->total, (string) $open->paid_amount, 2))
                            <tr data-bill="{{ $open->bill_no }}">
                                <td><a href="{{ route('procurement.bills.show', $open) }}">{{ $open->bill_no }}</a> <span class="small text-body-secondary">{{ $open->vendor_reference }}</span></td><td>{{ $open->due_on->format('d M Y') }}</td>
                                <td class="text-end font-monospace">{{ number_format((float) $balance, 2) }}</td>
                                <td style="width:10rem"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" name="allocations[{{ $open->id }}]" value="{{ old('allocations.'.$open->id, $billId === $open->id ? $balance : '') }}" aria-label="{{ __('Apply') }}"></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-body-secondary">{{ __('No approved bills are waiting for payment.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
            <div class="d-flex gap-2"><button type="submit" class="btn btn-primary" data-save-payment>{{ __('Record payment') }}</button><a href="{{ route('procurement.payments.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a></div>
        </form>
    @endif
</x-layouts::app>
