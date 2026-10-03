@php($title = $roomType ? $roomType->name : __('New room type'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Room types') => route('property.room-types.index'), $title => null]">
    <div class="row">
        <div class="col-xl-8">
            <form method="POST" action="{{ $roomType ? route('property.room-types.update', $roomType) : route('property.room-types.store') }}">
                @csrf
                @if ($roomType)
                    @method('PUT')
                @endif

                <x-card :title="__('Room type')" icon="bi-door-open">
                    <div class="row">
                        <div class="col-md-3"><x-form.input name="code" :label="__('Code')" :value="$roomType?->code" required :help="__('Short, e.g. DK.')" /></div>
                        <div class="col-md-9"><x-form.input name="name" :label="__('Name')" :value="$roomType?->name" required /></div>
                        <div class="col-md-6"><x-form.input name="bed_configuration" :label="__('Beds')" :value="$roomType?->bed_configuration" :help="__('E.g. 1 king, or 2 single.')" /></div>
                        <div class="col-md-3"><x-form.input name="size_sqm" type="number" step="0.01" min="0" :label="__('Size (m²)')" :value="$roomType?->size_sqm" /></div>
                        <div class="col-md-3"><x-form.input name="sort_order" type="number" min="0" :label="__('Sort order')" :value="$roomType?->sort_order ?? 0" /></div>
                    </div>
                </x-card>

                <x-card :title="__('Occupancy')" icon="bi-people">
                    <div class="row">
                        <div class="col-md-3"><x-form.input name="base_occupancy" type="number" min="1" :label="__('Base guests')" :value="$roomType?->base_occupancy ?? 2" required :help="__('Included in the room rate.')" /></div>
                        <div class="col-md-3"><x-form.input name="max_adults" type="number" min="1" :label="__('Max adults')" :value="$roomType?->max_adults ?? 2" required /></div>
                        <div class="col-md-3"><x-form.input name="max_children" type="number" min="0" :label="__('Max children')" :value="$roomType?->max_children ?? 1" required /></div>
                        <div class="col-md-3"><x-form.input name="max_occupancy" type="number" min="1" :label="__('Max guests')" :value="$roomType?->max_occupancy ?? 3" required :help="__('Adults and children together.')" /></div>
                    </div>
                </x-card>

                <x-card :title="__('Details')" icon="bi-card-text">
                    <x-form.field name="description" :label="__('Description')">
                        <textarea name="description" id="field-description" rows="3" @class(['form-control', 'is-invalid' => $errors->has('description')])>{{ old('description', $roomType?->description) }}</textarea>
                    </x-form.field>
                    <x-form.select name="amenity_ids" :label="__('Amenities')" :options="$amenities" :value="$selectedAmenities" multiple />
                    @include('property::partials.switch', ['name' => 'is_active', 'label' => __('Active (can be used for new rooms)'), 'checked' => $roomType?->is_active ?? true])
                </x-card>

                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('property.room-types.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save room type') }}</button>
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            @if ($roomType)
                <x-photo-gallery :subject="$roomType" />
                <x-audit-trail :entries="$history" />
            @else
                <x-card :title="__('Photos')" icon="bi-images">
                    <p class="text-body-secondary mb-0">{{ __('Save the room type first, then add photos.') }}</p>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts::app>
