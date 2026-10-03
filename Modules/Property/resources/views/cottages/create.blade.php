<x-layouts::app :title="__('New cottage with rooms')" :subtitle="$propertyName" :breadcrumbs="[__('Cottages') => route('property.cottages.index'), __('New cottage') => null]">
    @if ($cottageTypes === [] || $roomTypes === [])
        <x-card>
            <x-empty-state icon="bi-houses" :title="__('Add the types first')" :message="__('A cottage needs a cottage type, and its rooms need a room type.')">
                @can('create', \Modules\Property\Models\CottageType::class)
                    <a href="{{ route('property.cottage-types.create') }}" class="btn btn-sm btn-outline-primary">{{ __('New cottage type') }}</a>
                @endcan
                @can('create', \Modules\Property\Models\RoomType::class)
                    <a href="{{ route('property.room-types.create') }}" class="btn btn-sm btn-outline-primary">{{ __('New room type') }}</a>
                @endcan
            </x-empty-state>
        </x-card>
    @else
        <form method="POST" action="{{ route('property.cottages.store') }}" data-cottage-form>
            @csrf
            <div class="row">
                <div class="col-xl-7">
                    <x-card :title="__('Cottage')" icon="bi-house">
                        @include('property::cottages.partials.fields', ['cottage' => null])
                    </x-card>
                </div>
                <div class="col-xl-5">
                    <x-card :title="__('Rooms')" icon="bi-door-open">
                        <p class="text-body-secondary small">{{ __('A single-room cottage is a cottage with one room. You can add, change or remove rooms later.') }}</p>
                        <div class="row">
                            <div class="col-md-6"><x-form.input name="room_count" type="number" min="1" max="30" :label="__('Number of rooms')" value="1" required /></div>
                            <div class="col-md-6"><x-form.input name="first_room_number" :label="__('First room number')" required :help="__('The rest count on: 101, 102…')" /></div>
                            <div class="col-md-6"><x-form.select name="room_type_id" :label="__('Room type')" :options="$roomTypes" required :placeholder="__('Choose…')" /></div>
                            <div class="col-md-6"><x-form.input name="floor" :label="__('Floor')" /></div>
                        </div>
                    </x-card>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mb-4">
                <a href="{{ route('property.cottages.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('Create cottage and rooms') }}</button>
            </div>
        </form>
    @endif
</x-layouts::app>
