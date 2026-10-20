<x-layouts::app :title="$data['title']" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Payroll') => null, __('Reports') => route('payroll.reports.index'), $data['title'] => null]">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end mb-3" data-report-filter>
        @if ($report === 'cost')
            <x-form.input name="from" type="month" :label="__('From')" :value="$filters['from']" wrapper-class="mb-0" />
            <x-form.input name="to" type="month" :label="__('To')" :value="$filters['to']" wrapper-class="mb-0" />
        @else
            <x-form.input name="year" type="number" min="2000" max="2100" :label="__('Year')" :value="$filters['year']" wrapper-class="mb-0" />
        @endif
        <button type="submit" class="btn btn-primary">{{ __('Show') }}</button>
        <a class="btn btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" data-export-csv><i class="bi bi-download"></i> {{ __('CSV') }}</a>
    </form>

    <x-report-table :data="$data" :name="'payroll-'.$report" />
</x-layouts::app>
