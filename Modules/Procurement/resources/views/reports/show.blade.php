<x-layouts::app :title="$data['title']" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Reports') => null]">
    <ul class="nav nav-tabs mb-3" data-report-tabs>
        @foreach ($tabs as $key => $label)
            <li class="nav-item"><a class="nav-link @if ($key === $report) active @endif" href="{{ route('procurement.reports.'.$key) }}" data-report-tab="{{ $key }}">{{ $label }}</a></li>
        @endforeach
    </ul>

    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end mb-3" data-report-filter>
        @if ($withVendor)
            <x-form.select name="vendor" :label="__('Vendor')" :options="$vendors" :value="$vendorId" :placeholder="$report === 'vendor-ledger' ? __('Choose a vendor') : __('All vendors')" wrapper-class="mb-0" />
        @endif
        @if ($withDates)
            <x-form.input name="from" type="date" :label="__('From')" :value="$from" wrapper-class="mb-0" />
            <x-form.input name="to" type="date" :label="__('To')" :value="$to" wrapper-class="mb-0" />
        @elseif ($report === 'vendor-aging')
            <x-form.input name="as_of" type="date" :label="__('As at')" :value="$asOf" wrapper-class="mb-0" />
        @endif
        <button type="submit" class="btn btn-primary">{{ __('Show') }}</button>
        <a class="btn btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" data-export-csv><i class="bi bi-download"></i> {{ __('CSV') }}</a>
    </form>

    <x-report-table :data="$data" :name="$report" />
</x-layouts::app>
