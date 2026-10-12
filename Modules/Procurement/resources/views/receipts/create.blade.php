@php($qty = fn (?string $amount): string => rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.') ?: '0')
<x-layouts::app :title="__('Receive goods for :no', ['no' => $order->order_no])" :subtitle="$order->vendor->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Purchase orders') => route('procurement.orders.index'), $order->order_no => route('procurement.orders.show', $order), __('Receive') => null]">
    <form method="POST" action="{{ route('procurement.receipts.store', $order) }}" data-receipt-form>
        @csrf
        <x-card :title="__('Delivery')" icon="bi-truck">
            <div class="row">
                <div class="col-md-3"><x-form.date name="received_on" :label="__('Received on')" :value="old('received_on', now()->toDateString())" /></div>
                <div class="col-md-3"><x-form.input name="delivery_note" :label="__('Delivery note')" :value="old('delivery_note')" /></div>
                <div class="col-md-3"><x-form.input name="notes" :label="__('Notes')" :value="old('notes')" /></div>
                <div class="col-md-3"><label class="form-label">{{ __('Into store') }}</label><div class="form-control-plaintext">{{ $store ?? '—' }}</div></div>
            </div>
            <table class="table table-sm align-middle mb-0" data-receipt-lines>
                <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Ordered') }}</th><th class="text-end">{{ __('Received so far') }}</th><th class="text-end">{{ __('Accepted now') }}</th><th class="text-end">{{ __('Rejected') }}</th><th>{{ __('Reason for rejection') }}</th></tr></thead>
                <tbody>
                    @foreach ($order->lines as $line)
                        @php($left = bcsub((string) $line->quantity, (string) $line->received_quantity, 4))
                        <tr data-line="{{ $items[$line->item_id]->sku ?? $line->item_id }}">
                            <td>{{ $items[$line->item_id]->name ?? '#'.$line->item_id }} <span class="small text-body-secondary">{{ $items[$line->item_id]->sku ?? '' }} · {{ $items[$line->item_id]->baseUnit ?? '' }}</span></td>
                            <td class="text-end font-monospace">{{ $qty((string) $line->quantity) }}</td><td class="text-end font-monospace">{{ $qty((string) $line->received_quantity) }}</td>
                            <td style="width:9rem"><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" name="lines[{{ $line->id }}][accepted_quantity]" value="{{ old('lines.'.$line->id.'.accepted_quantity', bccomp($left, '0', 4) > 0 ? $qty($left) : '') }}" aria-label="{{ __('Accepted now') }}"></td>
                            <td style="width:8rem"><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" name="lines[{{ $line->id }}][rejected_quantity]" value="{{ old('lines.'.$line->id.'.rejected_quantity') }}" aria-label="{{ __('Rejected') }}"></td>
                            <td><input class="form-control form-control-sm" name="lines[{{ $line->id }}][reject_reason]" value="{{ old('lines.'.$line->id.'.reject_reason') }}" maxlength="150" aria-label="{{ __('Reason for rejection') }}"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
        <div class="d-flex gap-2"><button type="submit" class="btn btn-primary" data-receive><i class="bi bi-box-arrow-in-down"></i> {{ __('Receive into stock') }}</button><a href="{{ route('procurement.orders.show', $order) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a></div>
    </form>
</x-layouts::app>
