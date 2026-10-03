<x-layouts::app :title="__('Cottages')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Cottages') => null]">
    @can('create', \Modules\Property\Models\Cottage::class)
        <x-slot:actions>
            <a href="{{ route('property.cottages.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New cottage with rooms') }}</a>
        </x-slot:actions>
    @endcan

    <x-card>
        <x-datatable id="cottages-table" :url="route('property.cottages.data')" :columns="$columns" :order="[[0, 'asc']]" :empty-text="__('No cottages yet.')" />
    </x-card>
</x-layouts::app>
