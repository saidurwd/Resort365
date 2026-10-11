<x-layouts::app :title="__('Adjust stock')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Stock on hand') => route('inventory.stock.index'), __('Adjust stock') => null]">
    <form method="POST" action="{{ route('inventory.adjustments.store') }}" data-adjustment-form>
        @csrf
        <x-card :title="__('Stock found or lost')" icon="bi-sliders">
            <div class="row">
                <div class="col-md-4"><x-form.select name="store_id" :label="__('Store')" :options="$stores" :value="old('store_id')" :placeholder="__('Choose a store')" required /></div>
                <div class="col-md-8"><x-form.select name="item_id" :label="__('Item')" :options="$items" :value="old('item_id')" :placeholder="__('Choose an item')" required /></div>
            </div>
            <div class="row">
                <div class="col-md-3"><x-form.select name="direction" :label="__('Direction')" :options="['loss' => __('Stock lost (take out)'), 'gain' => __('Stock found (put in)')]" :value="old('direction', 'loss')" :search="false" required /></div>
                <div class="col-md-3"><x-form.input name="quantity" type="number" step="0.0001" min="0.0001" :label="__('Quantity (base unit)')" :value="old('quantity')" required /></div>
                <div class="col-md-3"><x-form.select name="reason" :label="__('Reason')" :options="$reasons" :value="old('reason')" :placeholder="__('Choose a reason')" :search="false" required /></div>
                <div class="col-md-3"><x-form.input name="unit_cost" type="number" step="0.0001" min="0" :label="__('Cost per unit (stock found)')" :value="old('unit_cost')" :help="__('Blank: the standard or average cost.')" /></div>
            </div>
            <x-form.input name="notes" :label="__('Notes')" :value="old('notes')" />
        </x-card>
        <div class="d-flex gap-2"><button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> {{ __('Adjust') }}</button><a href="{{ route('inventory.stock.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a></div>
    </form>
</x-layouts::app>
