@php($editing = $bill !== null)
@php($goods = $kind === \Modules\Procurement\Enums\VendorBillKind::Purchase)
@php($qty = fn (?string $amount): string => rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.') ?: '0')
<x-layouts::app :title="$editing ? __('Change bill :no', ['no' => $bill->bill_no]) : ($goods ? __('Bill for order :no', ['no' => $order->order_no]) : __('Service or expense bill'))" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Vendor bills') => route('procurement.bills.index'), $editing ? $bill->bill_no : __('New') => null]">
    <form method="POST" action="{{ $editing ? route('procurement.bills.update', $bill) : route('procurement.bills.store') }}" x-data="{ lines: @js($serviceLines) }" data-bill-form>
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="kind" value="{{ $kind->value }}">
        @if ($goods)<input type="hidden" name="purchase_order_id" value="{{ $order->id }}">@endif
        <x-card :title="__('Invoice')" icon="bi-receipt">
            <div class="row">
                <div class="col-md-4">
                    @if ($goods)<input type="hidden" name="vendor_id" value="{{ $order->vendor_id }}"><label class="form-label">{{ __('Vendor') }}</label><div class="form-control-plaintext">{{ $order->vendor->name }}</div>
                    @else<x-form.select name="vendor_id" :label="__('Vendor')" :options="$vendors" :value="old('vendor_id', $bill?->vendor_id)" :placeholder="__('Choose a vendor')" required />@endif
                </div>
                <div class="col-md-3"><x-form.input name="vendor_reference" :label="__('Their invoice number')" :value="old('vendor_reference', $bill?->vendor_reference)" /></div>
                <div class="col-md-2"><x-form.date name="bill_date" :label="__('Invoice date')" :value="old('bill_date', $bill?->bill_date?->toDateString() ?? now()->toDateString())" /></div>
                <div class="col-md-2"><x-form.date name="due_on" :label="__('Due on')" :value="old('due_on', $bill?->due_on?->toDateString())" /></div>
                @unless ($goods)<div class="col-md-4"><x-form.select name="department_id" :label="__('Department')" :options="$departments" :value="old('department_id', $bill?->department_id)" :placeholder="__('None')" /></div>@endunless
                <div class="col-md-8"><x-form.input name="notes" :label="__('Notes')" :value="old('notes', $bill?->notes)" /></div>
            </div>
            @if ($goods)
                <table class="table table-sm align-middle mb-0" data-bill-lines>
                    <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Received') }}</th><th class="text-end">{{ __('Already billed') }}</th><th class="text-end">{{ __('Open to bill') }}</th><th class="text-end">{{ __('Order price') }}</th><th class="text-end">{{ __('Bill quantity') }}</th><th class="text-end">{{ __('Billed price') }}</th></tr></thead>
                    <tbody>
                        @foreach ($orderRows as $index => $row)
                            <tr data-line="{{ $row['id'] }}">
                                <td>{{ $row['item'] }} <span class="small text-body-secondary">{{ $row['unit'] }}</span><input type="hidden" name="lines[{{ $index }}][purchase_order_line_id]" value="{{ $row['id'] }}"></td>
                                <td class="text-end font-monospace">{{ $qty($row['received']) }}</td><td class="text-end font-monospace">{{ $qty($row['billed']) }}</td><td class="text-end font-monospace">{{ $qty($row['open']) }}</td>
                                <td class="text-end font-monospace">{{ number_format((float) $row['po_price'], 4) }}</td>
                                <td style="width:9rem"><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" name="lines[{{ $index }}][quantity]" value="{{ old('lines.'.$index.'.quantity', $row['quantity'] !== '' ? $qty((string) $row['quantity']) : '') }}" aria-label="{{ __('Bill quantity') }}"></td>
                                <td style="width:9rem"><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" name="lines[{{ $index }}][unit_price]" value="{{ old('lines.'.$index.'.unit_price', $qty((string) $row['unit_price'])) }}" aria-label="{{ __('Billed price') }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($canOverride)
                    <div class="form-check mt-3"><input type="hidden" name="override_match" value="0"><input class="form-check-input" type="checkbox" id="override-match" name="override_match" value="1" @checked(old('override_match', $bill?->match_overridden))><label class="form-check-label" for="override-match">{{ __('Accept a price above the order price (override the 3-way match)') }}</label></div>
                @endif
            @else
                <template x-for="(row, index) in lines" :key="index">
                    <div class="row g-2 align-items-end mb-2" data-line>
                        <div class="col-md-3"><input class="form-control form-control-sm" :name="`lines[${index}][description]`" x-model="row.description" placeholder="{{ __('Description') }}" aria-label="{{ __('Description') }}" required></div>
                        <div class="col-md-2"><select class="form-select form-select-sm" :name="`lines[${index}][expense_class]`" x-model="row.expense_class" aria-label="{{ __('Expense') }}">@foreach ($classes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                        <div class="col-md-1"><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" :name="`lines[${index}][quantity]`" x-model="row.quantity" aria-label="{{ __('Quantity') }}"></div>
                        <div class="col-md-2"><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" :name="`lines[${index}][unit_price]`" x-model="row.unit_price" placeholder="{{ __('Unit price') }}" aria-label="{{ __('Unit price') }}" required></div>
                        <div class="col-md-3"><select class="form-select form-select-sm" :name="`lines[${index}][tax_category_id]`" x-model="row.tax_category_id" aria-label="{{ __('Tax') }}"><option value="">{{ __('No tax') }}</option>@foreach ($taxCategories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
                        <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" @click="lines.splice(index, 1)" :disabled="lines.length < 2" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div>
                    </div>
                </template>
                <button type="button" class="btn btn-sm btn-outline-primary" @click="lines.push({ description: '', expense_class: 'other', quantity: '1', unit_price: '', tax_category_id: '' })" data-add-line><i class="bi bi-plus-lg"></i> {{ __('Add line') }}</button>
            @endif
        </x-card>
        <div class="d-flex gap-2"><button type="submit" class="btn btn-primary" data-save-bill>{{ __('Save draft') }}</button><a href="{{ $editing ? route('procurement.bills.show', $bill) : route('procurement.bills.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a></div>
    </form>
</x-layouts::app>
