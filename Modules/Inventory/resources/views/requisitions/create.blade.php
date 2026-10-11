<x-layouts::app :title="__('New requisition')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Requisitions') => route('inventory.requisitions.index'), __('New') => null]">
    <form method="POST" action="{{ route('inventory.requisitions.store') }}" x-data="{ lines: @js($lines) }">
        @csrf
        <x-card :title="__('Request')" icon="bi-clipboard-check">
            <div class="row">
                <div class="col-md-3"><x-form.select name="department_id" :label="__('Department')" :options="$departments" :value="old('department_id')" :placeholder="__('Choose a department')" required /></div>
                <div class="col-md-3"><x-form.select name="store_id" :label="__('Ask this store')" :options="$stores" :value="old('store_id')" :placeholder="__('Choose a store')" required /></div>
                <div class="col-md-2"><x-form.date name="needed_on" :label="__('Needed by')" :value="old('needed_on')" /></div>
                <div class="col-md-4"><x-form.input name="notes" :label="__('Notes')" :value="old('notes')" /></div>
            </div>
            <template x-for="(row, index) in lines" :key="index">
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-8"><select class="form-select form-select-sm" :name="`lines[${index}][item_id]`" x-model="row.item_id" aria-label="{{ __('Item') }}" required><option value="">{{ __('Choose an item') }}</option>@foreach ($items as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-3"><input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm text-end" :name="`lines[${index}][quantity]`" x-model="row.quantity" placeholder="{{ __('Quantity') }}" aria-label="{{ __('Quantity') }}" required></div>
                    <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" @click="lines.splice(index, 1)" :disabled="lines.length < 2" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div>
                </div>
            </template>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="lines.push({ item_id: '', quantity: '' })"><i class="bi bi-plus-lg"></i> {{ __('Add item') }}</button>
        </x-card>
        <div class="d-flex gap-2"><button type="submit" class="btn btn-primary" data-submit-requisition><i class="bi bi-send"></i> {{ __('Submit') }}</button><a href="{{ route('inventory.requisitions.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a></div>
    </form>
</x-layouts::app>
