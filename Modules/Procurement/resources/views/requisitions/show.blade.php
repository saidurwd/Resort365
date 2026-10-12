<x-layouts::app :title="$requisition->requisition_no" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Requisitions') => route('procurement.requisitions.index'), $requisition->requisition_no => null]">
    <x-slot:actions>
        @if ($canWithdraw)
            <form method="POST" action="{{ route('procurement.requisitions.withdraw', $requisition) }}" class="d-inline" data-confirm="{{ __('Withdraw this requisition?') }}" data-confirm-variant="danger">@csrf<button type="submit" class="btn btn-outline-danger" data-withdraw><i class="bi bi-x-circle"></i> {{ __('Withdraw') }}</button></form>
        @endif
    </x-slot:actions>
    <div class="row">
        <div class="col-xl-8">
            <x-card :title="__('Items')" icon="bi-box-seam" body-class="p-0">
                <table class="table table-sm align-middle mb-0" data-requisition-lines>
                    <thead><tr><th class="ps-3">{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Estimated unit cost') }}</th><th class="text-end pe-3">{{ __('Estimated value') }}</th></tr></thead>
                    <tbody>
                        @foreach ($requisition->lines as $line)
                            <tr data-line="{{ $items[$line->item_id]->sku ?? $line->item_id }}">
                                <td class="ps-3">{{ $items[$line->item_id]->name ?? '#'.$line->item_id }} <span class="small text-body-secondary">{{ $items[$line->item_id]->sku ?? '' }}</span></td>
                                <td class="text-end font-monospace">{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }} {{ $items[$line->item_id]->baseUnit ?? '' }}</td>
                                <td class="text-end font-monospace">{{ number_format((float) $line->estimated_unit_cost, 4) }}</td>
                                <td class="text-end font-monospace pe-3">{{ number_format((float) bcmul((string) $line->quantity, (string) $line->estimated_unit_cost, 4), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
            @if ($canRfq)
                <x-card :title="__('Ask vendors for quotations')" icon="bi-envelope-paper">
                    <form method="POST" action="{{ route('procurement.rfqs.store') }}" data-rfq-form>
                        @csrf
                        <input type="hidden" name="purchase_requisition_id" value="{{ $requisition->id }}">
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label" for="rfq-vendors">{{ __('Vendors (two or more)') }}</label><select class="form-select" id="rfq-vendors" name="vendors[]" multiple size="5" required>@foreach ($vendors as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
                            <div class="col-md-3"><x-form.date name="due_on" :label="__('Reply by')" /></div>
                            <div class="col-md-3"><x-form.input name="notes" :label="__('Notes')" /></div>
                        </div>
                        <button type="submit" class="btn btn-outline-primary" data-create-rfq>{{ __('Prepare RFQ') }}</button>
                    </form>
                </x-card>
            @endif
            @if ($canOrder)
                <x-card :title="__('Order from one vendor')" icon="bi-cart-plus">
                    <form method="POST" action="{{ route('procurement.orders.from-requisition') }}" class="row" data-order-form>
                        @csrf
                        <input type="hidden" name="purchase_requisition_id" value="{{ $requisition->id }}">
                        <div class="col-md-4"><x-form.select name="vendor_id" :label="__('Vendor')" :options="$vendors" :placeholder="__('Choose a vendor')" required /></div>
                        <div class="col-md-4"><x-form.select name="store_id" :label="__('Receiving store')" :options="$stores" :placeholder="__('Choose a store')" required /></div>
                        <div class="col-md-4"><x-form.date name="expected_on" :label="__('Expected on')" /></div>
                        <div class="col-12"><button type="submit" class="btn btn-outline-primary" data-create-order>{{ __('Draft the order') }}</button></div>
                    </form>
                </x-card>
            @endif
        </div>
        <div class="col-xl-4">
            <x-card :title="__('Requisition')" icon="bi-clipboard-check">
                <dl class="row mb-0" data-requisition-summary>
                    <dt class="col-5">{{ __('Status') }}</dt><dd class="col-7"><x-status-badge :status="$requisition->status" /></dd>
                    <dt class="col-5">{{ __('Source') }}</dt><dd class="col-7"><x-status-badge :status="$requisition->source" /></dd>
                    <dt class="col-5">{{ __('Department') }}</dt><dd class="col-7">{{ $department ?? '—' }}</dd>
                    <dt class="col-5">{{ __('Estimated value') }}</dt><dd class="col-7 font-monospace">{{ number_format((float) $requisition->estimated_value, 2) }}</dd>
                    @if ($requisition->needed_on)<dt class="col-5">{{ __('Needed by') }}</dt><dd class="col-7">{{ $requisition->needed_on->format('d M Y') }}</dd>@endif
                    @if ($approval)<dt class="col-5">{{ __('Approval') }}</dt><dd class="col-7"><a href="{{ $approval->url }}">{{ $approval->status->label() }}@if ($approval->currentStep) · {{ $approval->currentStep }}@endif</a></dd>@endif
                    @if ($requisition->notes)<dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7">{{ $requisition->notes }}</dd>@endif
                </dl>
            </x-card>
            @if ($rfqs->isNotEmpty() || $orders->isNotEmpty())
                <x-card :title="__('Follow-up')" icon="bi-diagram-3" body-class="p-0">
                    <ul class="list-group list-group-flush">
                        @foreach ($rfqs as $rfq)<li class="list-group-item d-flex justify-content-between"><a href="{{ route('procurement.rfqs.show', $rfq) }}">{{ $rfq->rfq_no }}</a><x-status-badge :status="$rfq->status" /></li>@endforeach
                        @foreach ($orders as $order)<li class="list-group-item d-flex justify-content-between"><a href="{{ route('procurement.orders.show', $order) }}">{{ $order->order_no }}</a><x-status-badge :status="$order->status" /></li>@endforeach
                    </ul>
                </x-card>
            @endif
            <x-audit-trail :entries="$trail" />
        </div>
    </div>
</x-layouts::app>
