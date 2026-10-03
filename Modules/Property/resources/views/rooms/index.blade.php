<x-layouts::app :title="__('Rooms')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Rooms') => null]">
    @can('create', \Modules\Property\Models\Room::class)
        <x-slot:actions>
            <a href="{{ route('property.rooms.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New room') }}</a>
        </x-slot:actions>
    @endcan

    <x-card>
        <x-datatable id="rooms-table" :url="route('property.rooms.data')" :columns="$columns" :order="[[0, 'asc']]" :empty-text="__('No rooms yet.')" />
    </x-card>
</x-layouts::app>
