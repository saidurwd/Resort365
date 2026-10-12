@php($qty = fn (?string $amount): string => rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.') ?: '0')
<x-layouts::app :title="$receipt->receipt_no" :subtitle="$receipt->vendor->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Goods receipts') => route('procurement.receipts.index'), $receipt->receipt_no => null]">
    <x-slot:actions>
        @if ($canReturn)<a href="{{ route('procurement.returns.create', $receipt) }}" class="btn btn-outline-secondary" data-return><i class="bi bi-arrow-return-left"></i> {{ __('Return goods') }}</a>@endif
    </x-slot:actions>
    <div class="row">
        <div class="col-xl-8">
            <x-card :title="__('Items')" icon="bi-box-seam" body-class="p-0">
                <table class="table table-sm align-middle mb-0" data-receipt-lines>
                    <thead><tr><th class="ps-3">{{ __('Item') }}</th><th class="text-end">{{ __('Accepted') }}</th><th class="text-end">{{ __('Rejected') }}</th><th class="text-end">{{ __('Returned') }}</th><th class="text-end">{{ __('Cost') }}</th><th class="text-end pe-3">{{ __('Value') }}</th></tr></thead>
                    <tbody>
                        @foreach ($receipt->lines as $line)
                            <tr data-line="{{ $items[$line->item_id]->sku ?? $line->item_id }}">
                                <td class="ps-3">{{ $items[$line->item_id]->name ?? '#'.$line->item_id }} <span class="small text-body-secondary">{{ $items[$line->item_id]->sku ?? '' }}</span></td>
                                <td class="text-end font-monospace">{{ $qty((string) $line->accepted_quantity) }} {{ $items[$line->item_id]->baseUnit ?? '' }}</td>
                                <td class="text-end font-monospace">{{ $qty((string) $line->rejected_quantity) }}@if ($line->reject_reason)<div class="small text-body-secondary">{{ $line->reject_reason }}</div>@endif</td>
                                <td class="text-end font-monospace">{{ $qty((string) $line->returned_quantity) }}</td><td class="text-end font-monospace">{{ number_format((float) $line->unit_cost, 4) }}</td>
                                <td class="text-end font-monospace pe-3">{{ number_format((float) $line->value, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><th class="ps-3" colspan="5">{{ __('Total') }}</th><th class="text-end font-monospace pe-3" data-total>{{ number_format((float) $receipt->total_value, 2) }}</th></tr></tfoot>
                </table>
            </x-card>
        </div>
        <div class="col-xl-4">
            <x-card :title="__('Receipt')" icon="bi-truck">
                <dl class="row mb-0" data-receipt-summary>
                    <dt class="col-5">{{ __('Order') }}</dt><dd class="col-7"><a href="{{ route('procurement.orders.show', $receipt->order) }}">{{ $receipt->order->order_no }}</a></dd>
                    <dt class="col-5">{{ __('Vendor') }}</dt><dd class="col-7">{{ $receipt->vendor->name }}</dd>
                    <dt class="col-5">{{ __('Received') }}</dt><dd class="col-7">{{ $receipt->received_on->format('d M Y') }}</dd>
                    <dt class="col-5">{{ __('Store') }}</dt><dd class="col-7">{{ $store ?? '—' }}</dd>
                    @if ($receipt->delivery_note)<dt class="col-5">{{ __('Delivery note') }}</dt><dd class="col-7">{{ $receipt->delivery_note }}</dd>@endif
                    @if ($receipt->notes)<dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7">{{ $receipt->notes }}</dd>@endif
                </dl>
            </x-card>
            <x-audit-trail :entries="$trail" />
        </div>
    </div>
</x-layouts::app>
