<x-layouts::app :title="__('Goods receipts')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Goods receipts') => null]">
    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-receipts>
                <thead><tr><th class="ps-3">{{ __('Receipt') }}</th><th>{{ __('Order') }}</th><th>{{ __('Vendor') }}</th><th>{{ __('Received') }}</th><th>{{ __('Delivery note') }}</th><th class="text-end">{{ __('Value') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($receipts as $receipt)
                        <tr data-receipt="{{ $receipt->receipt_no }}">
                            <td class="ps-3">{{ $receipt->receipt_no }}</td><td>{{ $receipt->order->order_no }}</td><td>{{ $receipt->vendor->name }}</td><td>{{ $receipt->received_on->format('d M Y') }}</td>
                            <td>{{ $receipt->delivery_note ?? '—' }}</td><td class="text-end font-monospace">{{ number_format((float) $receipt->total_value, 2) }}</td>
                            <td class="text-end pe-3"><a href="{{ route('procurement.receipts.show', $receipt) }}" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state :title="__('No goods received yet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts::app>
