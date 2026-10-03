<x-layouts::app :title="__('Amenities')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Amenities') => null]">
    @can('create', \Modules\Property\Models\Amenity::class)
        <x-slot:actions>
            <a href="{{ route('property.amenities.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New amenity') }}</a>
        </x-slot:actions>
    @endcan

    <x-card body-class="p-0">
        @if ($amenities->isEmpty())
            <x-empty-state icon="bi-stars" :title="__('No amenities yet')" :message="__('The catalogue is shared by all your properties.')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-amenities>
                    <thead>
                        <tr>
                            <th class="ps-3">{{ __('Amenity') }}</th>
                            <th>{{ __('Category') }}</th>
                            <th class="text-end">{{ __('Cottage types') }}</th>
                            <th class="text-end">{{ __('Room types') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($amenities as $amenity)
                            <tr>
                                <td class="ps-3">@if ($amenity->icon)<i class="bi {{ $amenity->icon }} me-1"></i>@endif {{ $amenity->name }}</td>
                                <td><x-status-badge :status="$amenity->category" /></td>
                                <td class="text-end">{{ $amenity->cottage_types_count }}</td>
                                <td class="text-end">{{ $amenity->room_types_count }}</td>
                                <td>@include('property::partials.active-badge', ['active' => $amenity->is_active])</td>
                                <td class="text-end pe-3 text-nowrap">
                                    @can('update', $amenity)
                                        <a href="{{ route('property.amenities.edit', $amenity) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                    @endcan
                                    @can('delete', $amenity)
                                        <x-confirm-delete :action="route('property.amenities.destroy', $amenity)" icon-only :title="__('Delete amenity :name?', ['name' => $amenity->name])" :text="__('It is removed from every cottage type and room type.')" />
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts::app>
