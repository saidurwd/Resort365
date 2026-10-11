<x-layouts::app :title="__('Opening balances')" :subtitle="__('What a store held when the system started')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Stock on hand') => route('inventory.stock.index'), __('Opening balances') => null]">
    <form method="POST" action="{{ route('inventory.openings.store') }}" data-opening-form x-data="{ lines: @js($lines) }">
        @csrf
        <x-card :title="__('Store and items')" icon="bi-flag">
            <div class="row"><div class="col-md-4"><x-form.select name="store_id" :label="__('Store')" :options="$stores" :value="old('store_id')" :placeholder="__('Choose a store')" required /></div></div>
            <p class="small text-body-secondary">{{ __('Quantity in the item\'s base unit and the cost of one base unit. An item can have one opening balance per store; after that use an adjustment.') }}</p>
            <template x-for="(row, index) in lines" :key="index">
                <div class="row g-2 align-items-end mb-2" data-line>
                    <div class="col-md-6"><select class="form-select form-select-sm" :name="`lines[${index}][item_id]`" x-model="row.item_id" aria-label="{{ __('Item') }}" required><option value="">{{ __('Choose an item') }}</option>@foreach ($items as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-2"><input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm text-end" :name="`lines[${index}][quantity]`" x-model="row.quantity" placeholder="{{ __('Quantity') }}" aria-label="{{ __('Quantity') }}" required></div>
                    <div class="col-md-3"><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" :name="`lines[${index}][unit_cost]`" x-model="row.unit_cost" placeholder="{{ __('Cost per unit') }}" aria-label="{{ __('Cost per unit') }}" required></div>
                    <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" @click="lines.splice(index, 1)" :disabled="lines.length < 2" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div>
                </div>
            </template>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="lines.push({ item_id: '', quantity: '', unit_cost: '' })" data-add-line><i class="bi bi-plus-lg"></i> {{ __('Add item') }}</button>
        </x-card>
        <div class="d-flex gap-2"><button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> {{ __('Record') }}</button><a href="{{ route('inventory.stock.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a></div>
    </form>
</x-layouts::app>
