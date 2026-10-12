@php($qty = fn (?string $amount): string => rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.') ?: '0')
<x-layouts::app :title="$return->return_no" :subtitle="$return->vendor->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Returns') => route('procurement.returns.index'), $return->return_no => null]">
    <div class="row">
        <div class="col-xl-8">
            <x-card :title="__('Items')" icon="bi-box-seam" body-class="p-0">
                <table class="table table-sm align-middle mb-0" data-return-lines>
                    <thead><tr><th class="ps-3">{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Not yet billed') }}</th><th class="text-end">{{ __('Billed') }}</th><th class="text-end">{{ __('Tax') }}</th><th class="text-end pe-3">{{ __('Value') }}</th></tr></thead>
                    <tbody>
                        @foreach ($return->lines as $line)
                            <tr data-line="{{ $items[$line->item_id]->sku ?? $line->item_id }}">
                                <td class="ps-3">{{ $items[$line->item_id]->name ?? '#'.$line->item_id }}</td><td class="text-end font-monospace">{{ $qty((string) $line->quantity) }}</td>
                                <td class="text-end font-monospace">{{ number_format((float) $line->grni_value, 2) }}</td><td class="text-end font-monospace">{{ number_format((float) $line->billed_value, 2) }}</td>
                                <td class="text-end font-monospace">{{ number_format((float) $line->tax_value, 2) }}</td><td class="text-end font-monospace pe-3">{{ number_format((float) $line->value, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><th class="ps-3" colspan="5">{{ __('Total credited (before tax)') }}</th><th class="text-end font-monospace pe-3" data-total>{{ number_format((float) $return->total_value, 2) }}</th></tr></tfoot>
                </table>
            </x-card>
        </div>
        <div class="col-xl-4">
            <x-card :title="__('Debit note :no', ['no' => $return->debit_note_no])" icon="bi-arrow-return-left">
                <dl class="row mb-0" data-return-summary>
                    <dt class="col-5">{{ __('Vendor') }}</dt><dd class="col-7">{{ $return->vendor->name }}</dd>
                    <dt class="col-5">{{ __('Receipt') }}</dt><dd class="col-7"><a href="{{ route('procurement.receipts.show', $return->receipt) }}">{{ $return->receipt->receipt_no }}</a></dd>
                    <dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ $return->returned_on->format('d M Y') }}</dd>
                    <dt class="col-5">{{ __('Reason') }}</dt><dd class="col-7">{{ $return->reason }}</dd>
                    @if ($return->notes)<dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7">{{ $return->notes }}</dd>@endif
                </dl>
            </x-card>
            <x-audit-trail :entries="$trail" />
        </div>
    </div>
</x-layouts::app>
