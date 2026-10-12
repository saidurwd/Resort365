@php($qty = fn (?string $amount): string => rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.') ?: '0')
<x-layouts::app :title="__('Return goods from :no', ['no' => $receipt->receipt_no])" :subtitle="$receipt->vendor->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Goods receipts') => route('procurement.receipts.index'), $receipt->receipt_no => route('procurement.receipts.show', $receipt), __('Return') => null]">
    <form method="POST" action="{{ route('procurement.returns.store', $receipt) }}" data-return-form>
        @csrf
        <x-card :title="__('Return to vendor')" icon="bi-arrow-return-left">
            <div class="row">
                <div class="col-md-5"><x-form.input name="reason" :label="__('Reason')" :value="old('reason')" required /></div>
                <div class="col-md-3"><x-form.date name="returned_on" :label="__('Returned on')" :value="old('returned_on', now()->toDateString())" /></div>
                <div class="col-md-4"><x-form.input name="notes" :label="__('Notes')" :value="old('notes')" /></div>
            </div>
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Accepted') }}</th><th class="text-end">{{ __('Already returned') }}</th><th class="text-end">{{ __('Return now') }}</th></tr></thead>
                <tbody>
                    @foreach ($receipt->lines as $line)
                        <tr data-line="{{ $items[$line->item_id]->sku ?? $line->item_id }}">
                            <td>{{ $items[$line->item_id]->name ?? '#'.$line->item_id }}</td><td class="text-end font-monospace">{{ $qty((string) $line->accepted_quantity) }}</td><td class="text-end font-monospace">{{ $qty((string) $line->returned_quantity) }}</td>
                            <td style="width:9rem"><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" name="lines[{{ $line->id }}]" value="{{ old('lines.'.$line->id) }}" aria-label="{{ __('Return now') }}"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
        <div class="d-flex gap-2"><button type="submit" class="btn btn-primary" data-send-return><i class="bi bi-send"></i> {{ __('Return and issue the debit note') }}</button><a href="{{ route('procurement.receipts.show', $receipt) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a></div>
    </form>
</x-layouts::app>
