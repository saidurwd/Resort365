<x-layouts::app :title="__('Write off food')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('Wastage') => route('restaurant.wastage.index'), __('New') => null]">
    <form method="POST" action="{{ route('restaurant.wastage.store') }}" data-wastage-form x-data="{ items: [{ item_id: '', quantity: '' }], dishes: [{ menu_item_id: '', portions: '' }] }">
        @csrf
        <x-card :title="__('What happened')" icon="bi-trash3">
            <div class="row">
                <div class="col-md-3"><x-form.select name="store_id" :label="__('Taken out of store')" :options="$stores" :value="old('store_id')" :placeholder="__('Choose a store')" required /></div>
                <div class="col-md-3"><x-form.select name="reason" :label="__('Reason')" :options="$reasons" :value="old('reason', 'spoiled')" required /></div>
                <div class="col-md-2"><x-form.date name="wasted_on" :label="__('Date')" :value="old('wasted_on', now()->toDateString())" /></div>
                <div class="col-md-4"><x-form.input name="notes" :label="__('Notes')" :value="old('notes')" /></div>
            </div>
        </x-card>
        <x-card :title="__('Ingredients')" icon="bi-box-seam">
            <template x-for="(row, index) in items" :key="index">
                <div class="row g-2 mb-2"><div class="col-md-7"><select class="form-select form-select-sm" :name="`items[${index}][item_id]`" x-model="row.item_id" aria-label="{{ __('Ingredient') }}"><option value="">{{ __('Ingredient') }}</option>@foreach ($items as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-3"><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" :name="`items[${index}][quantity]`" x-model="row.quantity" placeholder="{{ __('Quantity') }}" aria-label="{{ __('Quantity') }}"></div>
                    <div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-danger" @click="items.splice(index, 1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div></div>
            </template>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="items.push({ item_id: '', quantity: '' })"><i class="bi bi-plus-lg"></i> {{ __('Add ingredient') }}</button>
        </x-card>
        <x-card :title="__('Dishes (through their recipes)')" icon="bi-egg-fried">
            <template x-for="(row, index) in dishes" :key="index">
                <div class="row g-2 mb-2"><div class="col-md-7"><select class="form-select form-select-sm" :name="`dishes[${index}][menu_item_id]`" x-model="row.menu_item_id" aria-label="{{ __('Dish') }}"><option value="">{{ __('Dish') }}</option>@foreach ($dishes as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-3"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" :name="`dishes[${index}][portions]`" x-model="row.portions" placeholder="{{ __('Portions') }}" aria-label="{{ __('Portions') }}"></div>
                    <div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-danger" @click="dishes.splice(index, 1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div></div>
            </template>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="dishes.push({ menu_item_id: '', portions: '' })"><i class="bi bi-plus-lg"></i> {{ __('Add dish') }}</button>
        </x-card>
        <div class="d-flex gap-2"><button type="submit" class="btn btn-primary" data-save-wastage>{{ __('Write off') }}</button><a href="{{ route('restaurant.wastage.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a></div>
    </form>
</x-layouts::app>
