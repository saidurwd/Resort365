@props([
    'id',
    'url',
    'columns',
    'order' => [[0, 'asc']],
    'pageLength' => 25,
    'emptyText' => null,
])

{{--
    Server-side DataTable backed by a yajra/laravel-datatables endpoint.
    columns: list of ['data' => 'code', 'title' => __('Code'), 'orderable' => true, 'searchable' => true, 'className' => 'text-end']
    <x-datatable id="rooms-table" :url="route('property.rooms.data')" :columns="$columns" />
--}}
@php
    $config = [
        'ajax' => $url,
        'columns' => collect($columns)->map(fn (array $column) => array_filter([
            'data' => $column['data'],
            'name' => $column['name'] ?? $column['data'],
            'orderable' => $column['orderable'] ?? true,
            'searchable' => $column['searchable'] ?? true,
            'className' => $column['className'] ?? null,
        ], fn ($value) => $value !== null))->all(),
        'order' => $order,
        'pageLength' => $pageLength,
    ];
@endphp

<div class="table-responsive">
    <table id="{{ $id }}" data-datatable="{{ json_encode($config) }}" data-empty-text="{{ $emptyText ?? __('No records found.') }}" {{ $attributes->class(['table', 'table-striped', 'table-hover', 'align-middle', 'w-100']) }}>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th @class([$column['className'] ?? null])>{{ $column['title'] }}</th>
                @endforeach
            </tr>
        </thead>
    </table>
</div>
