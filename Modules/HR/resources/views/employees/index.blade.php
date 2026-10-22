<x-layouts::app :title="__('Employees')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('HR') => null, __('Employees') => null]">
    @can('create', \Modules\HR\Models\Employee::class)
        <x-slot:actions>
            <a href="{{ route('hr.employees.create') }}" class="btn btn-sm btn-primary" data-new-employee><i class="bi bi-person-plus"></i> {{ __('New employee') }}</a>
        </x-slot:actions>
    @endcan

    <x-card>
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
            <ul class="nav nav-pills" data-employee-filters>
                @foreach (['all' => __('Working'), 'active' => __('Confirmed'), 'probation' => __('On probation'), 'exited' => __('Left')] as $key => $label)
                    <li class="nav-item">
                        <a @class(['nav-link', 'active' => $filter === $key]) href="{{ route('hr.employees.index', array_filter(['filter' => $key === 'all' ? null : $key, 'department' => $department, 'search' => $search ?: null])) }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
            <form method="GET" action="{{ route('hr.employees.index') }}" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="filter" value="{{ $filter }}">
                <select name="department" class="form-select form-select-sm" aria-label="{{ __('Department') }}" data-submit-on-change>
                    <option value="">{{ __('All departments') }}</option>
                    @foreach ($departments as $id => $name)
                        <option value="{{ $id }}" @selected($department === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <p class="small text-body-secondary">{{ __('Search by employee number, phone or the start of a name.') }}</p>
        <x-datatable id="employees-table" :url="route('hr.employees.data', array_filter(['filter' => $filter === 'all' ? null : $filter, 'department' => $department]))" :columns="$columns" :order="[[0, 'asc']]" :search="$search" :empty-text="__('No employees found.')" />
    </x-card>
</x-layouts::app>
