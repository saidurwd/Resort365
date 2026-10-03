@php($title = $amenity ? $amenity->name : __('New amenity'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Amenities') => route('property.amenities.index'), $title => null]">
    <form method="POST" action="{{ $amenity ? route('property.amenities.update', $amenity) : route('property.amenities.store') }}">
        @csrf
        @if ($amenity)
            @method('PUT')
        @endif
        <div class="row">
            <div class="col-xl-6">
                <x-card :title="__('Amenity')" icon="bi-stars">
                    <div class="row">
                        <div class="col-md-8"><x-form.input name="name" :label="__('Name')" :value="$amenity?->name" required /></div>
                        <div class="col-md-4"><x-form.select name="category" :label="__('Category')" :options="\Modules\Property\Enums\AmenityCategory::options()" :value="$amenity?->category->value" required :search="false" /></div>
                        <div class="col-md-8"><x-form.input name="icon" :label="__('Icon')" :value="$amenity?->icon" :help="__('A Bootstrap Icons class, e.g. bi-wifi.')" /></div>
                        <div class="col-md-4"><x-form.input name="sort_order" type="number" min="0" :label="__('Sort order')" :value="$amenity?->sort_order ?? 0" /></div>
                    </div>
                    @include('property::partials.switch', ['name' => 'is_active', 'label' => __('Active'), 'checked' => $amenity?->is_active ?? true])
                </x-card>
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('property.amenities.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save amenity') }}</button>
                </div>
            </div>
        </div>
    </form>
</x-layouts::app>
