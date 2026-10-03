<x-layouts::app :title="__('Room types')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Room types') => null]">
    @can('create', \Modules\Property\Models\RoomType::class)
        <x-slot:actions>
            <a href="{{ route('property.room-types.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New room type') }}</a>
        </x-slot:actions>
    @endcan

    <x-card body-class="p-0">
        @if ($roomTypes->isEmpty())
            <x-empty-state icon="bi-door-open" :title="__('No room types yet')" :message="__('Add types such as Deluxe King or Twin, then add rooms.')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-room-types>
                    <thead>
                        <tr>
                            <th class="ps-3">{{ __('Room type') }}</th>
                            <th>{{ __('Beds') }}</th>
                            <th class="text-end">{{ __('Base / max guests') }}</th>
                            <th class="text-end">{{ __('Adults / children') }}</th>
                            <th>{{ __('Amenities') }}</th>
                            <th class="text-end">{{ __('Rooms') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roomTypes as $type)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $type->name }}</div>
                                    <div class="small text-body-secondary">{{ $type->code }}@if ($type->size_sqm) · {{ rtrim(rtrim($type->size_sqm, '0'), '.') }} m²@endif</div>
                                </td>
                                <td>{{ $type->bed_configuration ?? '—' }}</td>
                                <td class="text-end text-nowrap">{{ $type->base_occupancy }} / {{ $type->max_occupancy }}</td>
                                <td class="text-end text-nowrap">{{ $type->max_adults }} / {{ $type->max_children }}</td>
                                <td>@include('property::partials.amenity-badges', ['amenities' => $type->amenities])</td>
                                <td class="text-end">{{ $type->rooms_count }}</td>
                                <td>@include('property::partials.active-badge', ['active' => $type->is_active])</td>
                                <td class="text-end pe-3 text-nowrap">
                                    @can('update', $type)
                                        <a href="{{ route('property.room-types.edit', $type) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                    @endcan
                                    @can('delete', $type)
                                        <x-confirm-delete :action="route('property.room-types.destroy', $type)" icon-only :title="__('Delete room type :name?', ['name' => $type->name])" />
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
