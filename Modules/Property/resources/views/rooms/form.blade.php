@php($title = $room ? __('Room :number', ['number' => $room->number]) : __('New room'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Rooms') => route('property.rooms.index'), $title => null]">
    <div class="row">
        <div class="col-xl-8">
            <form method="POST" action="{{ $room ? route('property.rooms.update', $room) : route('property.rooms.store') }}">
                @csrf
                @if ($room)
                    @method('PUT')
                @endif

                <x-card :title="__('Room')" icon="bi-door-open">
                    <div class="row">
                        <div class="col-md-4"><x-form.input name="number" :label="__('Room number')" :value="$room?->number" required /></div>
                        <div class="col-md-8"><x-form.input name="name" :label="__('Name')" :value="$room?->name" :help="__('Optional, e.g. Master bedroom.')" /></div>
                        <div class="col-md-6"><x-form.select name="cottage_id" :label="__('Cottage')" :options="$cottages" :value="$cottageId" required :placeholder="__('Choose…')" /></div>
                        <div class="col-md-6"><x-form.select name="room_type_id" :label="__('Room type')" :options="$roomTypes" :value="$room?->room_type_id" required :placeholder="__('Choose…')" /></div>
                        <div class="col-md-4"><x-form.input name="floor" :label="__('Floor')" :value="$room?->floor" /></div>
                        <div class="col-md-4"><x-form.input name="sort_order" type="number" min="0" :label="__('Sort order')" :value="$room?->sort_order ?? 0" /></div>
                    </div>
                </x-card>

                <x-card :title="__('Occupancy override')" icon="bi-people">
                    <p class="text-body-secondary small">{{ __('Leave empty to use the room type\'s values. The room type\'s max guests still applies.') }}</p>
                    <div class="row">
                        <div class="col-md-6"><x-form.input name="max_adults" type="number" min="1" :label="__('Max adults')" :value="$room?->max_adults" /></div>
                        <div class="col-md-6"><x-form.input name="max_children" type="number" min="0" :label="__('Max children')" :value="$room?->max_children" /></div>
                    </div>
                    @include('property::partials.switch', ['name' => 'is_active', 'label' => __('Active (can be booked)'), 'checked' => $room?->is_active ?? true])
                </x-card>

                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ $cottageId ? route('property.cottages.show', $cottageId) : route('property.rooms.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save room') }}</button>
                </div>
            </form>
        </div>

        @if ($room)
            <div class="col-xl-4">
                <x-card :title="__('Status')" icon="bi-info-circle">
                    <dl class="row mb-0">
                        <dt class="col-6">{{ __('Housekeeping') }}</dt>
                        <dd class="col-6"><x-status-badge :status="$room->housekeeping_status" /></dd>
                        <dt class="col-6">{{ __('Occupancy') }}</dt>
                        <dd class="col-6 mb-0"><x-status-badge :status="$room->occupancy_status" /></dd>
                    </dl>
                </x-card>
                <x-audit-trail :entries="$history" />
            </div>
        @endif
    </div>
</x-layouts::app>
