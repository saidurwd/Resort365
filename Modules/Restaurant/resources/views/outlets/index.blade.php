<x-layouts::app :title="__('Outlets')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('Outlets') => null]">
    <x-slot:actions>
        @can('restaurant.outlet.manage')
            <a href="{{ route('restaurant.outlets.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New outlet') }}</a>
        @endcan
    </x-slot:actions>
    <x-card body-class="p-0">
        @if ($outlets->isEmpty())
            <x-empty-state icon="bi-cup-hot" :title="__('No outlets yet')" :message="__('Add the restaurants, bars and room service of this resort.')" />
        @else
            <table class="table table-hover align-middle mb-0" data-outlets>
                <thead><tr><th class="ps-3">{{ __('Outlet') }}</th><th>{{ __('Type') }}</th><th class="text-end">{{ __('Stations') }}</th><th class="text-end">{{ __('Terminals') }}</th><th class="text-end">{{ __('Tables') }}</th><th class="pe-3">{{ __('Active') }}</th></tr></thead>
                <tbody>
                    @foreach ($outlets as $outlet)
                        <tr data-outlet="{{ $outlet->code }}">
                            <td class="ps-3"><a href="{{ route('restaurant.outlets.show', $outlet) }}" class="fw-semibold">{{ $outlet->name }}</a> <span class="small text-body-secondary">{{ $outlet->code }}</span></td>
                            <td><x-status-badge :status="$outlet->type" /></td>
                            <td class="text-end">{{ $outlet->stations_count }}</td>
                            <td class="text-end">{{ $outlet->terminals_count }}</td>
                            <td class="text-end">{{ $outlet->tables_count }}</td>
                            <td class="pe-3">@if ($outlet->is_active)<span class="badge text-bg-success">{{ __('Yes') }}</span>@else<span class="badge text-bg-secondary">{{ __('No') }}</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>
</x-layouts::app>
