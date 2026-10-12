@php($editing = $order !== null)
<x-layouts::app :title="$editing ? __('Change order :no', ['no' => $order->order_no]) : __('New purchase order')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Purchase orders') => route('procurement.orders.index'), $editing ? $order->order_no : __('New') => null]">
    <form method="POST" action="{{ $editing ? route('procurement.orders.update', $order) : route('procurement.orders.store') }}" x-data="{ lines: @js($lines) }" data-order-form>
        @csrf
        @if ($editing) @method('PUT') @endif
        <x-card :title="__('Order')" icon="bi-cart3">
            <div class="row">
                <div class="col-md-4"><x-form.select name="vendor_id" :label="__('Vendor')" :options="$vendors" :value="old('vendor_id', $order?->vendor_id)" :placeholder="__('Choose a vendor')" required /></div>
                <div class="col-md-4"><x-form.select name="store_id" :label="__('Receiving store')" :options="$stores" :value="old('store_id', $order?->store_id)" :placeholder="__('Choose a store')" required /></div>
                <div class="col-md-4"><x-form.select name="department_id" :label="__('Department')" :options="$departments" :value="old('department_id', $order?->department_id)" :placeholder="__('None')" /></div>
                <div class="col-md-3"><x-form.date name="ordered_on" :label="__('Order date')" :value="old('ordered_on', $order?->ordered_on?->toDateString())" /></div>
                <div class="col-md-3"><x-form.date name="expected_on" :label="__('Expected on')" :value="old('expected_on', $order?->expected_on?->toDateString())" /></div>
                <div class="col-md-2"><x-form.input name="payment_terms_days" type="number" :label="__('Terms (days)')" :value="old('payment_terms_days', $order?->payment_terms_days)" :help="__('Blank = the vendor\'s terms')" /></div>
                <div class="col-md-4"><x-form.input name="notes" :label="__('Notes')" :value="old('notes', $order?->notes)" /></div>
            </div>
            <template x-for="(row, index) in lines" :key="index">
                <div class="row g-2 align-items-end mb-2" data-line>
                    <div class="col-md-5"><select class="form-select form-select-sm" :name="`lines[${index}][item_id]`" x-model="row.item_id" aria-label="{{ __('Item') }}" required><option value="">{{ __('Choose an item') }}</option>@foreach ($items as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-2"><input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm text-end" :name="`lines[${index}][quantity]`" x-model="row.quantity" placeholder="{{ __('Quantity') }}" aria-label="{{ __('Quantity') }}" required></div>
                    <div class="col-md-2"><input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm text-end" :name="`lines[${index}][unit_price]`" x-model="row.unit_price" placeholder="{{ __('Unit price') }}" aria-label="{{ __('Unit price') }}" required></div>
                    <div class="col-md-2"><select class="form-select form-select-sm" :name="`lines[${index}][tax_category_id]`" x-model="row.tax_category_id" aria-label="{{ __('Tax') }}"><option value="">{{ __('No tax') }}</option>@foreach ($taxCategories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
                    <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" @click="lines.splice(index, 1)" :disabled="lines.length < 2" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div>
                </div>
            </template>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="lines.push({ item_id: '', quantity: '', unit_price: '', tax_category_id: '' })" data-add-line><i class="bi bi-plus-lg"></i> {{ __('Add item') }}</button>
        </x-card>
        <div class="d-flex gap-2"><button type="submit" class="btn btn-primary" data-save-order>{{ __('Save draft') }}</button><a href="{{ $editing ? route('procurement.orders.show', $order) : route('procurement.orders.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a></div>
    </form>
</x-layouts::app>
