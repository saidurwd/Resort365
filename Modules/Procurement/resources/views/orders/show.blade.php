@php($qty = fn (?string $amount): string => rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.'))
<x-layouts::app :title="$order->order_no" :subtitle="$order->vendor->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Purchase orders') => route('procurement.orders.index'), $order->order_no => null]">
    <x-slot:actions>
        <a href="{{ route('procurement.orders.pdf', $order) }}" class="btn btn-outline-secondary" data-pdf><i class="bi bi-file-earmark-pdf"></i> {{ __('PDF') }}</a>
        @if ($canEdit)
            <a href="{{ route('procurement.orders.edit', $order) }}" class="btn btn-outline-secondary" data-edit-order><i class="bi bi-pencil"></i> {{ __('Change') }}</a>
            <form method="POST" action="{{ route('procurement.orders.submit', $order) }}" class="d-inline">@csrf<button type="submit" class="btn btn-primary" data-submit-order><i class="bi bi-send-check"></i> {{ __('Send for approval') }}</button></form>
        @endif
        @if ($canCancel)
            <form method="POST" action="{{ route('procurement.orders.cancel', $order) }}" class="d-inline" data-confirm="{{ __('Cancel this order?') }}" data-confirm-variant="danger">@csrf<button type="submit" class="btn btn-outline-danger" data-cancel><i class="bi bi-x-circle"></i> {{ __('Cancel order') }}</button></form>
        @endif
    </x-slot:actions>
    <div class="row">
        <div class="col-xl-8">
            <x-card :title="__('Items')" icon="bi-box-seam" body-class="p-0">
                <table class="table table-sm align-middle mb-0" data-order-lines>
                    <thead><tr><th class="ps-3">{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Unit price') }}</th><th>{{ __('Tax') }}</th><th class="text-end">{{ __('Tax amount') }}</th><th class="text-end pe-3">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                        @foreach ($order->lines as $line)
                            <tr data-line="{{ $items[$line->item_id]->sku ?? $line->item_id }}">
                                <td class="ps-3">{{ $items[$line->item_id]->name ?? '#'.$line->item_id }} <span class="small text-body-secondary">{{ $items[$line->item_id]->sku ?? '' }}</span></td>
                                <td class="text-end font-monospace">{{ $qty((string) $line->quantity) }} {{ $items[$line->item_id]->baseUnit ?? '' }}</td>
                                <td class="text-end font-monospace">{{ number_format((float) $line->unit_price, 4) }}</td>
                                <td>{{ $taxCategories[$line->tax_category_id] ?? '—' }}</td>
                                <td class="text-end font-monospace">{{ number_format((float) $line->tax_amount, 2) }}</td>
                                <td class="text-end font-monospace pe-3">{{ number_format((float) $line->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><th class="ps-3" colspan="5">{{ __('Subtotal') }}</th><th class="text-end font-monospace pe-3">{{ number_format((float) $order->subtotal, 2) }}</th></tr>
                        <tr><th class="ps-3" colspan="5">{{ __('Taxes') }}</th><th class="text-end font-monospace pe-3">{{ number_format((float) $order->tax_total, 2) }}</th></tr>
                        <tr><th class="ps-3" colspan="5">{{ __('Total') }} ({{ $order->currency }})</th><th class="text-end font-monospace pe-3" data-total>{{ number_format((float) $order->total, 2) }}</th></tr>
                    </tfoot>
                </table>
            </x-card>
            @if ($canSend)
                <x-card :title="__('Email to the vendor')" icon="bi-envelope">
                    <form method="POST" action="{{ route('procurement.orders.send', $order) }}" class="row g-2 align-items-end" data-send-form>
                        @csrf
                        <div class="col-md-8"><x-form.input name="to" type="email" :label="__('Send to')" :value="$order->sent_to" :help="__('Blank = the vendor\'s primary contact or email address')" /></div>
                        <div class="col-md-4 mb-3"><button type="submit" class="btn btn-success w-100" data-send-order><i class="bi bi-envelope-arrow-up"></i> {{ $order->sent_at ? __('Send again') : __('Send the PDF') }}</button></div>
                    </form>
                </x-card>
            @endif
        </div>
        <div class="col-xl-4">
            <x-card :title="__('Order')" icon="bi-cart3">
                <dl class="row mb-0" data-order-summary>
                    <dt class="col-5">{{ __('Status') }}</dt><dd class="col-7"><x-status-badge :status="$order->status" /></dd>
                    <dt class="col-5">{{ __('Vendor') }}</dt><dd class="col-7"><a href="{{ route('procurement.vendors.show', $order->vendor_id) }}">{{ $order->vendor->name }}</a></dd>
                    <dt class="col-5">{{ __('Order date') }}</dt><dd class="col-7">{{ $order->ordered_on->format('d M Y') }}</dd>
                    <dt class="col-5">{{ __('Expected') }}</dt><dd class="col-7">{{ $order->expected_on?->format('d M Y') ?? '—' }}</dd>
                    <dt class="col-5">{{ __('Store') }}</dt><dd class="col-7">{{ $store ?? '—' }}</dd>
                    @if ($department)<dt class="col-5">{{ __('Department') }}</dt><dd class="col-7">{{ $department }}</dd>@endif
                    <dt class="col-5">{{ __('Terms') }}</dt><dd class="col-7">{{ trans_choice(':count day|:count days', $order->payment_terms_days) }}</dd>
                    @if ($approval)<dt class="col-5">{{ __('Approval') }}</dt><dd class="col-7"><a href="{{ $approval->url }}">{{ $approval->status->label() }}@if ($approval->currentStep) · {{ $approval->currentStep }}@endif</a></dd>@endif
                    @if ($order->sent_at)<dt class="col-5">{{ __('Sent') }}</dt><dd class="col-7">{{ $order->sent_at->inPropertyTime()->format('d M Y H:i') }} → {{ $order->sent_to }}</dd>@endif
                    @if ($order->purchase_requisition_id)<dt class="col-5">{{ __('Requisition') }}</dt><dd class="col-7"><a href="{{ route('procurement.requisitions.show', $order->purchase_requisition_id) }}">{{ __('Open') }}</a></dd>@endif
                    @if ($order->notes)<dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7">{{ $order->notes }}</dd>@endif
                </dl>
            </x-card>
            <x-audit-trail :entries="$trail" />
        </div>
    </div>
</x-layouts::app>
