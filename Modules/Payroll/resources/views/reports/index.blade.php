<x-layouts::app :title="__('Payroll reports')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Payroll') => null, __('Reports') => null]">
    <div class="row g-3" data-payroll-reports>
        <div class="col-md-4">
            <x-card :title="__('Payroll cost')">
                <p class="text-body-secondary small">{{ __('Gross pay, tax, net pay and employer cost by department over a range of months.') }}</p>
                <a href="{{ route('payroll.reports.cost') }}" class="btn btn-sm btn-primary" data-report="cost">{{ __('Open') }}</a>
            </x-card>
        </div>
        <div class="col-md-4">
            <x-card :title="__('Festival bonus register')">
                <p class="text-body-secondary small">{{ __('The festival bonuses paid in the year, by employee.') }}</p>
                <a href="{{ route('payroll.reports.festival') }}" class="btn btn-sm btn-primary" data-report="festival">{{ __('Open') }}</a>
            </x-card>
        </div>
        <div class="col-md-4">
            <x-card :title="__('Service charge and tips')">
                <p class="text-body-secondary small">{{ __('What was collected for staff, what was shared, and what payroll has paid out.') }}</p>
                <a href="{{ route('payroll.reports.pools') }}" class="btn btn-sm btn-primary" data-report="pools">{{ __('Open') }}</a>
            </x-card>
        </div>
    </div>
</x-layouts::app>
