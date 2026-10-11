@php($open = $transfer->status === \Modules\Inventory\Enums\TransferStatus::Dispatched)
<x-layouts::app :title="$transfer->transfer_no" :subtitle="$transfer->from->name.' → '.$transfer->to->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Transfers') => route('inventory.transfers.index'), $transfer->transfer_no => null]">
    <x-slot:actions>
        @if ($open && $canManage)
            <form method="POST" action="{{ route('inventory.transfers.receive', $transfer) }}" class="d-inline" data-confirm="{{ __('Receive all items into :store?', ['store' => $transfer->to->name]) }}">@csrf<button type="submit" class="btn btn-success" data-receive><i class="bi bi-box-arrow-in-down"></i> {{ __('Receive') }}</button></form>
            <form method="POST" action="{{ route('inventory.transfers.cancel', $transfer) }}" class="d-inline" data-confirm="{{ __('Call this transfer back to :store?', ['store' => $transfer->from->name]) }}" data-confirm-variant="danger">@csrf<button type="submit" class="btn btn-outline-danger" data-cancel><i class="bi bi-x-circle"></i> {{ __('Call back') }}</button></form>
        @endif
    </x-slot:actions>
    <div class="row">
        <div class="col-xl-8">
            <x-card :title="__('Items')" icon="bi-box-seam" body-class="p-0">
                <table class="table table-sm align-middle mb-0" data-transfer-lines>
                    <thead><tr><th class="ps-3">{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Unit cost') }}</th><th class="text-end pe-3">{{ __('Value') }}</th></tr></thead>
                    <tbody>
                        @foreach ($transfer->lines as $line)
                            <tr><td class="ps-3">{{ $line->item->name }} <span class="small text-body-secondary">{{ $line->item->sku }}</span></td>
                                <td class="text-end font-monospace">{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }} {{ $line->item->baseUnit->code }}</td>
                                <td class="text-end font-monospace">{{ number_format((float) $line->unit_cost, 4) }}</td>
                                <td class="text-end font-monospace pe-3">{{ number_format((float) bcmul((string) $line->quantity, (string) $line->unit_cost, 4), 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        </div>
        <div class="col-xl-4">
            <x-card :title="__('Transfer')" icon="bi-truck">
                <dl class="row mb-0" data-transfer-summary>
                    <dt class="col-5">{{ __('Status') }}</dt><dd class="col-7"><x-status-badge :status="$transfer->status" /></dd>
                    <dt class="col-5">{{ __('Sent') }}</dt><dd class="col-7">{{ $transfer->dispatched_at->inPropertyTime()->format('d M Y H:i') }}</dd>
                    @if ($transfer->received_at)<dt class="col-5">{{ __('Received') }}</dt><dd class="col-7">{{ $transfer->received_at->inPropertyTime()->format('d M Y H:i') }}</dd>@endif
                    @if ($transfer->cancelled_at)<dt class="col-5">{{ __('Called back') }}</dt><dd class="col-7">{{ $transfer->cancelled_at->inPropertyTime()->format('d M Y H:i') }}</dd>@endif
                    @if ($transfer->notes)<dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7">{{ $transfer->notes }}</dd>@endif
                </dl>
            </x-card>
            <x-audit-trail :entries="$trail" />
        </div>
    </div>
</x-layouts::app>
