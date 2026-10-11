<x-layouts::app :title="__('Stock movements')" :subtitle="__('The stock ledger')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Movements') => null]">
    <form method="GET" action="{{ route('inventory.movements.index') }}" class="d-flex flex-wrap gap-2 align-items-end mb-3" data-movement-filter>
        <x-form.select name="store" :label="__('Store')" :options="$stores" :value="request('store')" :placeholder="__('All stores')" :search="false" wrapper-class="mb-0" />
        <x-form.select name="item" :label="__('Item')" :options="$items" :value="request('item')" :placeholder="__('All items')" wrapper-class="mb-0" />
        <x-form.select name="type" :label="__('Type')" :options="$types" :value="request('type')" :placeholder="__('Any')" :search="false" wrapper-class="mb-0" />
        <x-form.input name="from" type="date" :label="__('From')" :value="request('from')" wrapper-class="mb-0" />
        <x-form.input name="to" type="date" :label="__('To')" :value="request('to')" wrapper-class="mb-0" />
        <button type="submit" class="btn btn-outline-primary">{{ __('Show') }}</button>
    </form>
    <x-card body-class="p-0">
        <x-datatable id="movements" :url="route('inventory.movements.data', request()->only(['store', 'item', 'type', 'from', 'to']))" :columns="$columns" :order="[[0, 'desc']]" :empty-text="__('No stock movements yet.')" />
    </x-card>
    @if ($canReverse)
        <x-card :title="__('Reverse a movement')" icon="bi-arrow-counterclockwise">
            <form method="POST" id="reverse-form" class="row g-2 align-items-end" data-reverse-form
                x-data="{ id: '' }" :action="id ? @js(url('/inventory/movements')).concat('/', id, '/reverse') : '#'">
                @csrf
                <div class="col-md-2"><label class="form-label" for="rev-id">{{ __('Movement #') }}</label><input class="form-control" id="rev-id" type="number" min="1" x-model="id" required></div>
                <div class="col-md-6"><label class="form-label" for="rev-reason">{{ __('Reason') }}</label><input class="form-control" id="rev-reason" name="reason" maxlength="200" required></div>
                <div class="col-md-2"><button type="submit" class="btn btn-outline-danger" :disabled="! id">{{ __('Reverse') }}</button></div>
            </form>
        </x-card>
    @endif
</x-layouts::app>
