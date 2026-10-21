<x-layouts::app :title="__('Prices')" :breadcrumbs="[__('Setup checklist') => route('core.onboarding.index'), __('Prices') => null]">
    <div class="row">
        <div class="col-xl-7">
            <form method="POST" action="{{ route('rates.onboarding.prices.store') }}">
                @csrf
                <x-card :title="__('Price per night')" icon="bi-tags">
                    <p class="text-body-secondary">{{ __('One price for every night, before taxes. A 30% advance deposit and a flexible cancellation policy are set up with it. Add seasons, weekend prices and meal plans under Rates later.') }}</p>
                    @error('prices')<div class="alert alert-danger">{{ $message }}</div>@enderror
                    @foreach ($units as $unit)
                        <x-form.money :name="'prices['.$unit->key().']'" :label="$unit->name" :currency="$currency" :help="$unit->kind->label()" />
                    @endforeach
                </x-card>
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('core.onboarding.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save the prices') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
