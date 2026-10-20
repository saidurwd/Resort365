<x-layouts::app :title="__('HR reports')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('HR') => null, __('Reports') => null]">
    <div class="row g-3" data-hr-reports>
        <div class="col-md-4">
            <x-card :title="__('Headcount')">
                <p class="text-body-secondary small">{{ __('Who works here, by department and employment type, with the people who joined and left.') }}</p>
                <a href="{{ route('hr.reports.headcount') }}" class="btn btn-sm btn-primary" data-report="headcount">{{ __('Open') }}</a>
            </x-card>
        </div>
        <div class="col-md-4">
            <x-card :title="__('Attendance summary')">
                <p class="text-body-secondary small">{{ __('A month of attendance, late arrivals and overtime added up by department.') }}</p>
                <a href="{{ route('hr.reports.attendance') }}" class="btn btn-sm btn-primary" data-report="attendance">{{ __('Open') }}</a>
            </x-card>
        </div>
        <div class="col-md-4">
            <x-card :title="__('Leave balances')">
                <p class="text-body-secondary small">{{ __('The year\'s leave entitled, used, pending and available by department and leave type.') }}</p>
                <a href="{{ route('hr.reports.leave') }}" class="btn btn-sm btn-primary" data-report="leave">{{ __('Open') }}</a>
            </x-card>
        </div>
    </div>
</x-layouts::app>
