<x-layouts::app :title="__('Menu')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('Menu') => null]">
    <x-slot:actions>
        @can('restaurant.menu.manage')
            <a href="{{ route('restaurant.menu.import') }}" class="btn btn-outline-secondary"><i class="bi bi-upload"></i> {{ __('Import CSV') }}</a>
            <a href="{{ route('restaurant.menu.items.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New item') }}</a>
        @endcan
    </x-slot:actions>
    <form method="GET" class="d-flex gap-2 align-items-end mb-3">
        <x-form.select name="category" :label="__('Category')" :options="$categories" :value="$category" :placeholder="__('All categories')" wrapper-class="mb-0" />
        <button type="submit" class="btn btn-outline-primary">{{ __('Show') }}</button>
    </form>
    <x-card body-class="p-0">
        <x-datatable id="menu-items" :url="route('restaurant.menu.items.data', $category ? ['category' => $category] : [])" :columns="$columns" :order="[[0, 'asc']]" :empty-text="__('No menu items yet.')" />
    </x-card>
    <p class="small text-body-secondary">{{ __('Prices, stations and sold-out items are set per outlet: Restaurant → Outlets → an outlet → Price list.') }}</p>
</x-layouts::app>
