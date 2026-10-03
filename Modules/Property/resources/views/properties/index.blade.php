<x-layouts::app :title="__('Properties')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Properties') => null]">
    @can('create', \Modules\Property\Models\Property::class)
        <x-slot:actions>
            <a href="{{ route('property.properties.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New property') }}</a>
        </x-slot:actions>
    @endcan

    <x-card body-class="p-0">
        @if ($properties->isEmpty())
            <x-empty-state icon="bi-building" :title="__('No properties yet')" :message="__('Add your first resort to get started.')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-properties>
                    <thead>
                        <tr>
                            <th class="ps-3">{{ __('Property') }}</th>
                            <th>{{ __('Location') }}</th>
                            <th>{{ __('Check-in / out') }}</th>
                            <th>{{ __('Business date') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($properties as $property)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $property->name }}</div>
                                    <div class="small text-body-secondary">{{ $property->code }} · {{ $property->currency_code }} · {{ $property->timezone }}</div>
                                </td>
                                <td>{{ collect([$property->city, $property->country_code])->filter()->implode(', ') }}</td>
                                <td class="text-nowrap">{{ substr($property->check_in_time, 0, 5) }} / {{ substr($property->check_out_time, 0, 5) }}</td>
                                <td class="text-nowrap">{{ $property->business_date->format('d M Y') }}</td>
                                <td><x-status-badge :status="$property->status" /></td>
                                <td class="text-end pe-3">
                                    @can('update', $property)
                                        <a href="{{ route('property.properties.edit', $property) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
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
