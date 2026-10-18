<x-layouts::app :title="$data['title']" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Reports') => null]">
    <ul class="nav nav-tabs mb-3" data-report-tabs>
        @foreach ($tabs as $key => $label)
            <li class="nav-item"><a class="nav-link @if ($key === $report) active @endif" href="{{ route('inventory.reports.'.$key, ['store' => $storeId]) }}" data-report-tab="{{ $key }}">{{ $label }}</a></li>
        @endforeach
    </ul>

    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end mb-3" data-report-filter>
        <x-form.select name="store" :label="__('Store')" :options="$stores" :value="$storeId" :placeholder="__('All stores')" :search="false" wrapper-class="mb-0" />
        @if ($report === 'valuation')
            <x-form.input name="as_of" type="date" :label="__('As at')" :value="$asOf" wrapper-class="mb-0" />
        @elseif ($report === 'slow-moving')
            <x-form.input name="days" type="number" :label="__('Idle for (days)')" :value="$days" wrapper-class="mb-0" />
        @else
            <x-form.input name="from" type="date" :label="__('From')" :value="$from" wrapper-class="mb-0" />
            <x-form.input name="to" type="date" :label="__('To')" :value="$to" wrapper-class="mb-0" />
        @endif
        <button type="submit" class="btn btn-primary">{{ __('Show') }}</button>
        <a class="btn btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" data-export-csv><i class="bi bi-download"></i> {{ __('CSV') }}</a>
        @if ($report === 'valuation' && $canReconcile)<a class="btn btn-outline-secondary" href="{{ route('accounting.reports.show', ['report' => 'inventory']) }}" data-reconcile><i class="bi bi-arrow-left-right"></i> {{ __('Compare with the ledger') }}</a>@endif
    </form>

    <x-report-table :data="$data" :name="$report" />
</x-layouts::app>
