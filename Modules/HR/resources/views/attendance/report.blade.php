<x-layouts::app :title="$data['title']" :subtitle="$subtitle" :breadcrumbs="[__('HR') => null, __('Attendance') => route('hr.attendance.index'), __('Report') => null]">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end mb-3" data-report-filter>
        <x-form.input name="month" type="month" :label="__('Month')" :value="$month" wrapper-class="mb-0" />
        <x-form.select name="department" :label="__('Department')" :options="$departments" :value="$department" :placeholder="__('All departments')" wrapper-class="mb-0" />
        <button type="submit" class="btn btn-primary">{{ __('Show') }}</button>
        <a class="btn btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" data-export-csv><i class="bi bi-download"></i> {{ __('CSV') }}</a>
    </form>

    <x-report-table :data="$data" name="attendance" />
</x-layouts::app>
