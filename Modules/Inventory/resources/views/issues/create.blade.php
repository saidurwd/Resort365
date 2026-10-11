<x-layouts::app :title="__('Direct issue')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Direct issue') => null]">
    <form method="POST" action="{{ route('inventory.issues.store') }}" x-data="{ lines: @js($lines) }">
        @csrf
        <x-card :title="__('Issue to a department')" icon="bi-box-arrow-right">
            <div class="row">
                <div class="col-md-3"><x-form.select name="store_id" :label="__('From store')" :options="$stores" :value="old('store_id')" :placeholder="__('Choose a store')" required /></div>
                <div class="col-md-3"><x-form.select name="department_id" :label="__('To department')" :options="$departments" :value="old('department_id')" :placeholder="__('Choose a department')" required /></div>
                <div class="col-md-3"><x-form.input name="reason" :label="__('Reason')" :value="old('reason')" required /></div>
                <div class="col-md-3"><x-form.input name="notes" :label="__('Notes')" :value="old('notes')" /></div>
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
        <button type="submit" class="btn btn-primary" data-issue-stock><i class="bi bi-box-arrow-right"></i> {{ __('Issue') }}</button>
    </form>
</x-layouts::app>
