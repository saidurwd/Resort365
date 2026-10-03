<x-layouts::app :title="__('Edit :name', ['name' => $cottage->name])" :breadcrumbs="[__('Cottages') => route('property.cottages.index'), $cottage->name => route('property.cottages.show', $cottage), __('Edit') => null]">
    <form method="POST" action="{{ route('property.cottages.update', $cottage) }}">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col-xl-8">
                <x-card :title="__('Cottage')" icon="bi-house">
                    @include('property::cottages.partials.fields')
                </x-card>
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('property.cottages.show', $cottage) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save cottage') }}</button>
                </div>
            </div>
        </div>
    </form>
</x-layouts::app>
