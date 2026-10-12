@php($qty = fn (?string $amount): string => rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.') ?: '0')
<x-layouts::app :title="$bill->bill_no" :subtitle="$bill->vendor->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Vendor bills') => route('procurement.bills.index'), $bill->bill_no => null]">
    <x-slot:actions>
        @if ($canEdit)
            <a href="{{ route('procurement.bills.edit', $bill) }}" class="btn btn-outline-secondary" data-edit-bill><i class="bi bi-pencil"></i> {{ __('Change') }}</a>
            <form method="POST" action="{{ route('procurement.bills.submit', $bill) }}" class="d-inline">@csrf<button type="submit" class="btn btn-primary" data-submit-bill><i class="bi bi-send-check"></i> {{ __('Send for approval') }}</button></form>
        @endif
        @if ($canPay)<a href="{{ route('procurement.payments.create', ['vendor' => $bill->vendor_id, 'bill' => $bill->id]) }}" class="btn btn-success" data-pay><i class="bi bi-cash-coin"></i> {{ __('Pay') }}</a>@endif
        @if ($canCancel)
            <form method="POST" action="{{ route('procurement.bills.cancel', $bill) }}" class="d-inline" data-confirm="{{ __('Cancel this bill?') }}" data-confirm-variant="danger">@csrf<button type="submit" class="btn btn-outline-danger" data-cancel><i class="bi bi-x-circle"></i> {{ __('Cancel bill') }}</button></form>
        @endif
    </x-slot:actions>
    <div class="row">
        <div class="col-xl-8">
            <x-card :title="__('Lines')" icon="bi-receipt" body-class="p-0">
                <table class="table table-sm align-middle mb-0" data-bill-lines>
                    <thead><tr><th class="ps-3">{{ __('Description') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Unit price') }}</th><th>{{ __('Tax') }}</th><th class="text-end">{{ __('Tax amount') }}</th><th class="text-end pe-3">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                        @foreach ($bill->lines as $line)
                            <tr>
                                <td class="ps-3">{{ $line->item_id ? ($items[$line->item_id]->name ?? '#'.$line->item_id) : $line->description }}@if ($line->expense_class) <span class="badge text-bg-{{ $line->expense_class->color() }}">{{ $line->expense_class->label() }}</span>@endif</td>
                                <td class="text-end font-monospace">{{ $qty((string) $line->quantity) }}</td><td class="text-end font-monospace">{{ number_format((float) $line->unit_price, 4) }}</td>
                                <td>{{ $taxCategories[$line->tax_category_id] ?? '—' }}</td><td class="text-end font-monospace">{{ number_format((float) $line->tax_amount, 2) }}</td><td class="text-end font-monospace pe-3">{{ number_format((float) $line->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><th class="ps-3" colspan="5">{{ __('Subtotal') }}</th><th class="text-end font-monospace pe-3">{{ number_format((float) $bill->subtotal, 2) }}</th></tr>
                        <tr><th class="ps-3" colspan="5">{{ __('Taxes') }}</th><th class="text-end font-monospace pe-3">{{ number_format((float) $bill->tax_total, 2) }}</th></tr>
                        <tr><th class="ps-3" colspan="5">{{ __('Total') }} ({{ $bill->currency }})</th><th class="text-end font-monospace pe-3" data-total>{{ number_format((float) $bill->total, 2) }}</th></tr>
                    </tfoot>
                </table>
            </x-card>
        </div>
        <div class="col-xl-4">
            <x-card :title="__('Bill')" icon="bi-receipt">
                <dl class="row mb-0" data-bill-summary>
                    <dt class="col-5">{{ __('Status') }}</dt><dd class="col-7"><x-status-badge :status="$bill->status" /></dd>
                    <dt class="col-5">{{ __('Kind') }}</dt><dd class="col-7"><x-status-badge :status="$bill->kind" /></dd>
                    <dt class="col-5">{{ __('Vendor') }}</dt><dd class="col-7"><a href="{{ route('procurement.vendors.show', $bill->vendor_id) }}">{{ $bill->vendor->name }}</a></dd>
                    @if ($bill->order)<dt class="col-5">{{ __('Order') }}</dt><dd class="col-7"><a href="{{ route('procurement.orders.show', $bill->order) }}">{{ $bill->order->order_no }}</a></dd>@endif
                    @if ($bill->vendor_reference)<dt class="col-5">{{ __('Their invoice') }}</dt><dd class="col-7">{{ $bill->vendor_reference }}</dd>@endif
                    <dt class="col-5">{{ __('Invoice date') }}</dt><dd class="col-7">{{ $bill->bill_date->format('d M Y') }}</dd>
                    <dt class="col-5">{{ __('Due') }}</dt><dd class="col-7">{{ $bill->due_on->format('d M Y') }}</dd>
                    <dt class="col-5">{{ __('Paid') }}</dt><dd class="col-7 font-monospace">{{ number_format((float) $bill->paid_amount, 2) }} / {{ number_format((float) $bill->total, 2) }}</dd>
                    @if ($department)<dt class="col-5">{{ __('Department') }}</dt><dd class="col-7">{{ $department }}</dd>@endif
                    @if ($bill->match_overridden)<dt class="col-5">{{ __('3-way match') }}</dt><dd class="col-7 text-warning" data-match-overridden>{{ $bill->match_notes }}</dd>@endif
                    @if ($approval)<dt class="col-5">{{ __('Approval') }}</dt><dd class="col-7"><a href="{{ $approval->url }}">{{ $approval->status->label() }}@if ($approval->currentStep) · {{ $approval->currentStep }}@endif</a></dd>@endif
                    @if ($bill->notes)<dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7">{{ $bill->notes }}</dd>@endif
                </dl>
            </x-card>
            <x-audit-trail :entries="$trail" />
        </div>
    </div>
</x-layouts::app>
