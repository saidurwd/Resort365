<x-layouts::app :title="__('Reorder suggestions')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Reorder suggestions') => null]">
    @if ($suggestions->isEmpty())
        <x-card><x-empty-state icon="bi-emoji-smile" :title="__('Nothing is below its reorder level')" /></x-card>
    @else
        <form method="POST" action="{{ route('procurement.suggestions.store') }}" data-suggestion-form>
            @csrf
            @foreach ($suggestions as $store => $rows)
                <x-card :title="$store" icon="bi-boxes" body-class="p-0">
                    <table class="table table-sm align-middle mb-0" data-suggestions="{{ $store }}">
                        <thead><tr><th class="ps-3" style="width:2rem"></th><th>{{ __('Item') }}</th><th class="text-end">{{ __('On hand') }}</th><th class="text-end">{{ __('Reorder level') }}</th><th class="text-end">{{ __('Suggested') }}</th><th class="text-end">{{ __('Buy') }}</th></tr></thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr data-suggestion="{{ $row->sku }}">
                                    <td class="ps-3">@if ($canCreate)<input class="form-check-input" type="checkbox" name="items[]" value="{{ $row->itemId }}" aria-label="{{ __('Buy :item', ['item' => $row->itemName]) }}" @checked(in_array($row->itemId, (array) old('items', [])))>@endif</td>
                                    <td>{{ $row->itemName }} <span class="small text-body-secondary">{{ $row->sku }} · {{ $row->unit }}</span></td>
                                    <td class="text-end font-monospace">{{ rtrim(rtrim($row->onHand, '0'), '.') ?: '0' }}</td><td class="text-end font-monospace">{{ rtrim(rtrim($row->reorderLevel, '0'), '.') }}</td>
                                    <td class="text-end font-monospace">{{ rtrim(rtrim($row->suggestedQuantity, '0'), '.') }}</td>
                                    <td class="text-end pe-3" style="width:9rem">@if ($canCreate)<input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" name="quantities[{{ $row->itemId }}]" placeholder="{{ rtrim(rtrim($row->suggestedQuantity, '0'), '.') }}" aria-label="{{ __('Quantity to buy') }}">@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endforeach
            @if ($canCreate)
                <div class="row">
                    <div class="col-md-4"><x-form.select name="department_id" :label="__('Department')" :options="$departments" :placeholder="__('Choose a department')" /></div>
                </div>
                <button type="submit" class="btn btn-primary" data-create-requisition><i class="bi bi-clipboard-plus"></i> {{ __('Raise a requisition from the ticked items') }}</button>
            @endif
        </form>
    @endif
</x-layouts::app>
