@php($title = $cottageType ? $cottageType->name : __('New cottage type'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Cottage types') => route('property.cottage-types.index'), $title => null]">
    <div class="row">
        <div class="col-xl-8">
            <form method="POST" action="{{ $cottageType ? route('property.cottage-types.update', $cottageType) : route('property.cottage-types.store') }}">
                @csrf
                @if ($cottageType)
                    @method('PUT')
                @endif

                <x-card :title="__('Cottage type')" icon="bi-houses">
                    <div class="row">
                        <div class="col-md-3"><x-form.input name="code" :label="__('Code')" :value="$cottageType?->code" required :help="__('Short, e.g. FV.')" /></div>
                        <div class="col-md-9"><x-form.input name="name" :label="__('Name')" :value="$cottageType?->name" required /></div>
                        <div class="col-md-4"><x-form.input name="bedrooms" type="number" min="1" :label="__('Bedrooms')" :value="$cottageType?->bedrooms ?? 1" required /></div>
                        <div class="col-md-4"><x-form.input name="max_occupancy" type="number" min="1" :label="__('Max guests')" :value="$cottageType?->max_occupancy" required :help="__('When the whole cottage is booked.')" /></div>
                        <div class="col-md-4"><x-form.input name="sort_order" type="number" min="0" :label="__('Sort order')" :value="$cottageType?->sort_order ?? 0" /></div>
                        <div class="col-12">
                            <x-form.field name="description" :label="__('Description')">
                                <textarea name="description" id="field-description" rows="3" @class(['form-control', 'is-invalid' => $errors->has('description')])>{{ old('description', $cottageType?->description) }}</textarea>
                            </x-form.field>
                        </div>
                        <div class="col-12"><x-form.select name="amenity_ids" :label="__('Amenities')" :options="$amenities" :value="$selectedAmenities" multiple /></div>
                        <div class="col-12">@include('property::partials.switch', ['name' => 'is_active', 'label' => __('Active (can be used for new cottages)'), 'checked' => $cottageType?->is_active ?? true])</div>
                    </div>
                </x-card>

                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('property.cottage-types.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save cottage type') }}</button>
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            @if ($cottageType)
                <x-photo-gallery :subject="$cottageType" />
                <x-audit-trail :entries="$history" />
            @else
                <x-card :title="__('Photos')" icon="bi-images">
                    <p class="text-body-secondary mb-0">{{ __('Save the cottage type first, then add photos.') }}</p>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts::app>
