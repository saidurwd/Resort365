@php($qty = fn (?string $amount): string => rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.'))
<x-layouts::app :title="$rfq->rfq_no" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('RFQs') => route('procurement.rfqs.index'), $rfq->rfq_no => null]">
    <x-slot:actions>
        @if ($canManage && in_array($rfq->status, [\Modules\Procurement\Enums\RfqStatus::Draft, \Modules\Procurement\Enums\RfqStatus::Sent], true))
            <form method="POST" action="{{ route('procurement.rfqs.send', $rfq) }}" class="d-inline">@csrf<button type="submit" class="btn btn-primary" data-send-rfq><i class="bi bi-envelope"></i> {{ $rfq->status === \Modules\Procurement\Enums\RfqStatus::Draft ? __('Send to vendors') : __('Send again') }}</button></form>
            <form method="POST" action="{{ route('procurement.rfqs.cancel', $rfq) }}" class="d-inline" data-confirm="{{ __('Cancel this RFQ?') }}" data-confirm-variant="danger">@csrf<button type="submit" class="btn btn-outline-danger" data-cancel><i class="bi bi-x-circle"></i> {{ __('Cancel') }}</button></form>
        @endif
    </x-slot:actions>
    <div class="row">
        <div class="col-xl-9">
            <x-card :title="__('Comparison')" icon="bi-columns-gap" body-class="p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" data-comparison>
                        <thead>
                            <tr><th class="ps-3">{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th>@foreach ($rfq->quotations as $quotation)<th class="text-end" data-quotation="{{ $quotation->vendor->name }}">{{ $quotation->vendor->name }}@if ($comparison['cheapest'] === $quotation->id) <span class="badge text-bg-success">{{ __('cheapest') }}</span>@endif @if ($quotation->status !== \Modules\Procurement\Enums\QuotationStatus::Received)<x-status-badge :status="$quotation->status" />@endif</th>@endforeach</tr>
                        </thead>
                        <tbody>
                            @foreach ($asked as $itemId => $quantity)
                                <tr data-line="{{ $items[$itemId]->sku ?? $itemId }}">
                                    <td class="ps-3">{{ $items[$itemId]->name ?? '#'.$itemId }} <span class="small text-body-secondary">{{ $items[$itemId]->sku ?? '' }}</span></td>
                                    <td class="text-end font-monospace">{{ $qty($quantity) }} {{ $items[$itemId]->baseUnit ?? '' }}</td>
                                    @foreach ($rfq->quotations as $quotation)
                                        <td class="text-end font-monospace {{ ($comparison['best_price'][$itemId] ?? null) === $quotation->id ? 'fw-bold text-success' : '' }}">{{ isset($quotes[$quotation->id][$itemId]) ? number_format((float) $quotes[$quotation->id][$itemId], 4) : '—' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th class="ps-3" colspan="2">{{ __('Total at the quantities asked') }}</th>@foreach ($rfq->quotations as $quotation)<th class="text-end font-monospace" data-total="{{ $quotation->vendor->name }}">{{ number_format((float) $comparison['totals'][$quotation->id], 2) }}@if ($comparison['missing'][$quotation->id] !== [])<div class="small text-danger fw-normal">{{ trans_choice(':count item not quoted|:count items not quoted', count($comparison['missing'][$quotation->id])) }}</div>@endif</th>@endforeach</tr>
                            @if ($open && $canManage)
                                <tr><td class="ps-3" colspan="2"></td>@foreach ($rfq->quotations as $quotation)
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('procurement.rfqs.award', $rfq) }}" class="d-flex gap-1 justify-content-end" data-award-form="{{ $quotation->vendor->name }}">
                                            @csrf <input type="hidden" name="quotation_id" value="{{ $quotation->id }}">
                                            <select class="form-select form-select-sm w-auto" name="store_id" aria-label="{{ __('Receiving store') }}" required>@foreach ($stores as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                                            <button type="submit" class="btn btn-sm btn-success">{{ __('Select') }}</button>
                                        </form>
                                    </td>@endforeach</tr>
                            @endif
                        </tfoot>
                    </table>
                </div>
            </x-card>
            @if ($open && $canManage)
                <x-card :title="__('Enter a vendor\'s quotation')" icon="bi-pencil-square">
                    <form method="POST" action="{{ route('procurement.rfqs.quote', $rfq) }}" data-quotation-form>
                        @csrf
                        <div class="row">
                            <div class="col-md-4"><x-form.select name="vendor_id" :label="__('Vendor')" :options="$rfq->vendors->mapWithKeys(fn ($row) => [$row->vendor_id => $row->vendor->name])->all()" :placeholder="__('Choose a vendor')" required /></div>
                            <div class="col-md-3"><x-form.input name="reference" :label="__('Their reference')" /></div>
                            <div class="col-md-2"><x-form.date name="quoted_on" :label="__('Quoted on')" /></div>
                            <div class="col-md-3"><x-form.date name="valid_until" :label="__('Valid until')" /></div>
                        </div>
                        <table class="table table-sm align-middle">
                            <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Unit price') }}</th><th class="text-end">{{ __('Lead days') }}</th></tr></thead>
                            <tbody>
                                @foreach ($asked as $itemId => $quantity)
                                    <tr><td>{{ $items[$itemId]->name ?? '#'.$itemId }}</td><td class="text-end font-monospace">{{ $qty($quantity) }}</td>
                                        <td style="width:10rem"><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" name="prices[{{ $itemId }}]" data-price="{{ $items[$itemId]->sku ?? $itemId }}" aria-label="{{ __('Unit price') }}"></td>
                                        <td style="width:7rem"><input type="number" min="0" class="form-control form-control-sm text-end" name="lead_days[{{ $itemId }}]" aria-label="{{ __('Lead days') }}"></td></tr>
                                @endforeach
                            </tbody>
                        </table>
                        <button type="submit" class="btn btn-outline-primary" data-save-quotation>{{ __('Save quotation') }}</button>
                    </form>
                </x-card>
            @endif
        </div>
        <div class="col-xl-3">
            <x-card :title="__('RFQ')" icon="bi-envelope-paper">
                <dl class="row mb-0" data-rfq-summary>
                    <dt class="col-5">{{ __('Status') }}</dt><dd class="col-7"><x-status-badge :status="$rfq->status" /></dd>
                    <dt class="col-5">{{ __('Requisition') }}</dt><dd class="col-7"><a href="{{ route('procurement.requisitions.show', $rfq->requisition) }}">{{ $rfq->requisition->requisition_no }}</a></dd>
                    @if ($rfq->due_on)<dt class="col-5">{{ __('Reply by') }}</dt><dd class="col-7">{{ $rfq->due_on->format('d M Y') }}</dd>@endif
                    @if ($rfq->notes)<dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7">{{ $rfq->notes }}</dd>@endif
                </dl>
            </x-card>
            <x-card :title="__('Vendors asked')" icon="bi-shop" body-class="p-0">
                <ul class="list-group list-group-flush" data-rfq-vendors>
                    @foreach ($rfq->vendors as $row)
                        <li class="list-group-item d-flex justify-content-between"><span>{{ $row->vendor->name }}</span><span class="small text-body-secondary">{{ $row->sent_at ? __('sent') : __('not sent') }}</span></li>
                    @endforeach
                </ul>
            </x-card>
            <x-audit-trail :entries="$trail" />
        </div>
    </div>
</x-layouts::app>
