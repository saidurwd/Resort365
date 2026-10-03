<x-layouts::app :title="$cottage->name" :subtitle="$cottage->code.' · '.$cottage->cottageType->name" :breadcrumbs="[__('Cottages') => route('property.cottages.index'), $cottage->name => null]">
    <x-slot:actions>
        @can('update', $cottage)
            <a href="{{ route('property.cottages.edit', $cottage) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
        @endcan
        @can('delete', $cottage)
            <x-confirm-delete :action="route('property.cottages.destroy', $cottage)" :title="__('Delete cottage :name?', ['name' => $cottage->name])" :text="__('Only a cottage without rooms can be deleted.')" />
        @endcan
    </x-slot:actions>

    <div class="row">
        <div class="col-md-3 col-6"><x-stat-box :label="__('Rooms')" :value="$cottage->rooms->count()" icon="bi-door-open" color="primary" /></div>
        <div class="col-md-3 col-6"><x-stat-box :label="__('Max guests')" :value="$maxOccupancy" icon="bi-people" color="success" data-total-occupancy /></div>
        <div class="col-md-3 col-6"><x-stat-box :label="__('Bedrooms (type)')" :value="$cottage->cottageType->bedrooms" icon="bi-house" color="info" /></div>
        <div class="col-md-3 col-6"><x-stat-box :label="__('Zone')" :value="$cottage->zone ?? '—'" icon="bi-geo-alt" color="secondary" /></div>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <x-card :title="__('Rooms')" icon="bi-door-open" body-class="p-0">
                @can('create', \Modules\Property\Models\Room::class)
                    <x-slot:tools>
                        <a href="{{ route('property.rooms.create', ['cottage' => $cottage->id]) }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add room') }}</a>
                    </x-slot:tools>
                @endcan

                @if ($rooms->isEmpty())
                    <x-empty-state icon="bi-door-open" :title="__('No rooms in this cottage')" :message="__('Add a room so the cottage can be booked.')" />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" data-cottage-rooms>
                            <thead>
                                <tr>
                                    <th class="ps-3">{{ __('Room') }}</th>
                                    <th>{{ __('Room type') }}</th>
                                    <th>{{ __('Floor') }}</th>
                                    <th class="text-end">{{ __('Adults / children') }}</th>
                                    <th class="text-end">{{ __('Max guests') }}</th>
                                    <th>{{ __('Housekeeping') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="pe-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rooms as ['room' => $room, 'capacity' => $capacity])
                                    <tr @class(['text-body-secondary' => ! $room->is_active])>
                                        <td class="ps-3">
                                            <div class="fw-semibold">{{ $room->number }}</div>
                                            @if ($room->name)<div class="small text-body-secondary">{{ $room->name }}</div>@endif
                                        </td>
                                        <td>{{ $room->roomType->name }}</td>
                                        <td>{{ $room->floor ?? '—' }}</td>
                                        <td class="text-end">{{ $capacity->maxAdults }} / {{ $capacity->maxChildren }}</td>
                                        <td class="text-end">{{ $capacity->maxOccupancy }}</td>
                                        <td><x-status-badge :status="$room->housekeeping_status" /></td>
                                        <td>@include('property::partials.active-badge', ['active' => $room->is_active])</td>
                                        <td class="text-end pe-3 text-nowrap">@include('property::rooms.partials.actions')</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-semibold">
                                    <td class="ps-3" colspan="4">{{ __('Total (active rooms)') }}</td>
                                    <td class="text-end" data-rooms-occupancy>{{ $roomsOccupancy }}</td>
                                    <td colspan="3" class="small text-body-secondary fw-normal">
                                        @if ($cottage->max_occupancy_override !== null)
                                            {{ __('Overridden: the cottage takes :count guests.', ['count' => $cottage->max_occupancy_override]) }}
                                        @endif
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </x-card>

            <x-photo-gallery :subject="$cottage" />
        </div>

        <div class="col-xl-4">
            <x-card :title="__('Details')" icon="bi-info-circle">
                <dl class="row mb-0">
                    <dt class="col-5">{{ __('Booking mode') }}</dt>
                    <dd class="col-7"><x-status-badge :status="$cottage->booking_mode" /></dd>
                    <dt class="col-5">{{ __('Status') }}</dt>
                    <dd class="col-7"><x-status-badge :status="$cottage->status" /></dd>
                    <dt class="col-5">{{ __('Cottage type') }}</dt>
                    <dd class="col-7">{{ $cottage->cottageType->name }}</dd>
                    @if ($cottage->description)
                        <dt class="col-12">{{ __('Description') }}</dt>
                        <dd class="col-12 mb-0">{{ $cottage->description }}</dd>
                    @endif
                </dl>
            </x-card>

            <x-audit-trail :entries="$history" />
        </div>
    </div>
</x-layouts::app>
