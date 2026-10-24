<x-layouts::app :title="__('Rooms and cottages on the website')" :subtitle="$site->name" :breadcrumbs="[__('Website') => route('website.admin.site.edit'), __('Rooms and cottages') => null]">
    <form method="POST" action="{{ route('website.admin.units.update', $site) }}" data-units-form>
        @csrf
        @method('PUT')
        @error('units')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        @forelse ($units as $unit)
            @php($setting = $settings->get($unit->key()))
            @php($base = 'units['.$unit->key().']')
            <x-card :title="$unit->name" icon="{{ $unit->kind->value === 'room_type' ? 'bi-door-open' : 'bi-house' }}" data-unit-row="{{ $unit->key() }}">
                <p class="small text-body-secondary">
                    {{ $unit->kind->value === 'room_type' ? __('Room type') : __('Cottage type, booked whole') }} ·
                    {{ trans_choice('{1} sleeps 1|[2,*] sleeps :count', $unit->maxOccupancy, ['count' => $unit->maxOccupancy]) }} ·
                    {{ trans_choice('{0} no photos|{1} 1 photo|[2,*] :count photos', count($unit->photoIds), ['count' => count($unit->photoIds)]) }}
                    ({{ __('photos are added on the room or cottage type under Setup') }})
                </p>
                <div class="row">
                    @foreach ($locales as $code => $language)
                        <div class="col-md-6" lang="{{ $code }}">
                            <x-form.input :name="$base.'[headline]['.$code.']'" :label="__('Headline').' · '.$language" :value="$setting?->headline[$code] ?? null" />
                            <x-form.field :name="$base.'[description]['.$code.']'" :label="__('Description').' · '.$language" :help="$loop->first ? __('Empty: the description from Setup is shown.') : null">
                                <textarea name="{{ $base }}[description][{{ $code }}]" rows="3" class="form-control">{{ $setting?->description[$code] ?? null }}</textarea>
                            </x-form.field>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex flex-wrap gap-4 align-items-center">
                    <div class="form-check form-switch">
                        <input type="hidden" name="{{ $base }}[is_featured]" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="{{ $base }}[is_featured]" value="1" id="featured-{{ $loop->index }}" @checked($setting?->is_featured)>
                        <label class="form-check-label" for="featured-{{ $loop->index }}">{{ __('Featured on the home page') }}</label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="{{ $base }}[is_hidden]" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="{{ $base }}[is_hidden]" value="1" id="hidden-{{ $loop->index }}" @checked($setting?->is_hidden)>
                        <label class="form-check-label" for="hidden-{{ $loop->index }}">{{ __('Hidden from the website') }}</label>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0" for="order-{{ $loop->index }}">{{ __('Order') }}</label>
                        <input type="number" min="0" max="999" class="form-control form-control-sm website-order-input" name="{{ $base }}[sort_order]" id="order-{{ $loop->index }}" value="{{ $setting?->sort_order ?? $loop->index }}">
                    </div>
                </div>
            </x-card>
        @empty
            <x-empty-state :title="__('No rooms to show yet')" :message="__('Add active room types or cottage types under Setup first.')" icon="bi-house" />
        @endforelse

        @if ($units !== [])
            @can('update', $site)
                <div class="d-flex justify-content-end mb-4"><button type="submit" class="btn btn-primary">{{ __('Save') }}</button></div>
            @endcan
        @endif
    </form>
</x-layouts::app>
