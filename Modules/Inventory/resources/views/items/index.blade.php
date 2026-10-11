<x-layouts::app :title="__('Items')" :subtitle="__('The inventory catalogue')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Items') => null]">
    <x-slot:actions>
        @if ($canImport)<a href="{{ route('inventory.import.create') }}" class="btn btn-outline-secondary" data-import><i class="bi bi-upload"></i> {{ __('Import') }}</a>@endif
        @if ($canManage)<a href="{{ route('inventory.items.create') }}" class="btn btn-primary" data-new-item><i class="bi bi-plus-lg"></i> {{ __('New item') }}</a>@endif
    </x-slot:actions>

    <form method="GET" action="{{ route('inventory.items.index') }}" class="d-flex flex-wrap gap-2 align-items-end mb-3" data-item-filter>
        <x-form.select name="category" :label="__('Category')" :options="$categories" :value="request('category')" :placeholder="__('All categories')" wrapper-class="mb-0" />
        <x-form.select name="stockable" :label="__('Kind')" :options="['1' => __('Stocked items'), '0' => __('Services and non-stock')]" :value="request('stockable')" :placeholder="__('Any')" :search="false" wrapper-class="mb-0" />
        <x-form.select name="active" :label="__('Status')" :options="['1' => __('Active'), '0' => __('Inactive')]" :value="request('active')" :placeholder="__('Any')" :search="false" wrapper-class="mb-0" />
        <button type="submit" class="btn btn-outline-primary">{{ __('Show') }}</button>
    </form>
    <x-card body-class="p-0">
        <x-datatable id="items" :url="route('inventory.items.data', request()->only(['category', 'stockable', 'active']))" :columns="$columns" :order="[[1, 'asc']]" :empty-text="__('No items yet. Add one or import a CSV file.')" />
    </x-card>
</x-layouts::app>
