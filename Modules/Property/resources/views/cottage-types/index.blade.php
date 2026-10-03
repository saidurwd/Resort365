<x-layouts::app :title="__('Cottage types')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Cottage types') => null]">
    @can('create', \Modules\Property\Models\CottageType::class)
        <x-slot:actions>
            <a href="{{ route('property.cottage-types.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New cottage type') }}</a>
        </x-slot:actions>
    @endcan

    <x-card body-class="p-0">
        @if ($cottageTypes->isEmpty())
            <x-empty-state icon="bi-houses" :title="__('No cottage types yet')" :message="__('Add types such as Family Villa or Honeymoon Cottage, then add cottages.')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-cottage-types>
                    <thead>
                        <tr>
                            <th class="ps-3">{{ __('Cottage type') }}</th>
                            <th class="text-end">{{ __('Bedrooms') }}</th>
                            <th class="text-end">{{ __('Max guests') }}</th>
                            <th>{{ __('Amenities') }}</th>
                            <th class="text-end">{{ __('Cottages') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cottageTypes as $type)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $type->name }}</div>
                                    <div class="small text-body-secondary">{{ $type->code }}</div>
                                </td>
                                <td class="text-end">{{ $type->bedrooms }}</td>
                                <td class="text-end">{{ $type->max_occupancy }}</td>
                                <td>@include('property::partials.amenity-badges', ['amenities' => $type->amenities])</td>
                                <td class="text-end">{{ $type->cottages_count }}</td>
                                <td>@include('property::partials.active-badge', ['active' => $type->is_active])</td>
                                <td class="text-end pe-3 text-nowrap">
                                    @can('update', $type)
                                        <a href="{{ route('property.cottage-types.edit', $type) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                    @endcan
                                    @can('delete', $type)
                                        <x-confirm-delete :action="route('property.cottage-types.destroy', $type)" icon-only :title="__('Delete cottage type :name?', ['name' => $type->name])" />
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
