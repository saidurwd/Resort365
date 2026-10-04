<x-layouts::app :title="__('Work orders')" :subtitle="$property->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Housekeeping') => null, __('Work orders') => null]">
    <x-slot:actions>
        @can('housekeeping.work-order.create')
            <a href="{{ route('housekeeping.work-orders.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Report a fault') }}</a>
        @endcan
    </x-slot:actions>
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a @class(['nav-link', 'active' => ! $all]) href="{{ route('housekeeping.work-orders.index') }}">{{ __('Open') }}</a></li>
        <li class="nav-item"><a @class(['nav-link', 'active' => $all]) href="{{ route('housekeeping.work-orders.index', ['all' => 1]) }}">{{ __('All') }}</a></li>
    </ul>
    <x-card body-class="p-0">
        <x-datatable id="work-orders-table" :url="route('housekeeping.work-orders.data', $all ? ['all' => 1] : [])" :columns="$columns" :order="[[0, 'desc']]" :empty-text="__('No work orders.')" />
    </x-card>
</x-layouts::app>
