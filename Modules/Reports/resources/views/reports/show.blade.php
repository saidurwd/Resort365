<x-layouts::app :title="$table['title']" :subtitle="$filter->consolidated() ? __('All properties') : implode(', ', $propertyNames)" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Reports') => route('reports.index'), $definition->title => null]">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end mb-3" data-report-filter>
        @if (in_array('horizon', $definition->filters, true))
            <x-form.select name="horizon" :label="__('Look ahead')" :options="[30 => __('30 days'), 60 => __('60 days'), 90 => __('90 days')]" :value="$input['horizon'] ?? 30" :search="false" wrapper-class="mb-0" />
        @else
            <x-form.date name="from" :label="__('From')" :value="$filter->from" wrapper-class="mb-0" />
            <x-form.date name="to" :label="__('To')" :value="$filter->to" wrapper-class="mb-0" />
        @endif
        @if (in_array('by', $definition->filters, true))
            <x-form.select name="by" :label="__('Count by')" :options="['booked' => __('Booking date'), 'arrival' => __('Arrival date')]" :value="$filter->by" :search="false" wrapper-class="mb-0" />
        @endif
        @if (in_array('limit', $definition->filters, true))
            <x-form.input name="limit" type="number" min="1" max="100" :label="__('Show top')" :value="$filter->limit" wrapper-class="mb-0" />
        @endif
        @if ($canConsolidate)
            <x-form.select name="property" :label="__('Properties')" :options="['' => __('This property'), 'all' => __('All properties')]" :value="$filter->consolidated() ? 'all' : ''" :search="false" wrapper-class="mb-0" />
        @endif
        <button type="submit" class="btn btn-primary">{{ __('Show') }}</button>
        <a class="btn btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" data-export-csv><i class="bi bi-filetype-csv"></i> {{ __('CSV') }}</a>
        @if ($canExport)
            <a class="btn btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" data-export-xlsx><i class="bi bi-file-earmark-excel"></i> {{ __('Excel') }}</a>
            <a class="btn btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" data-export-pdf><i class="bi bi-file-earmark-pdf"></i> {{ __('PDF') }}</a>
        @endif
    </form>

    <x-report-table :data="$table" :name="$definition->key" />
</x-layouts::app>
