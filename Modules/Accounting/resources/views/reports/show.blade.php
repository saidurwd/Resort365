{{--
    A financial report (Step 4.5): the filter bar, the table with drill-down links on its figures, and the exports.
--}}
<x-layouts::app :title="$title" :subtitle="$report?->subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Financial reports') => route('accounting.reports.index'), $title => null]">
    <x-slot:actions>
        @if ($report && $canExport)
            @foreach (['xlsx' => 'Excel', 'csv' => 'CSV', 'pdf' => 'PDF'] as $format => $label)
                <a href="{{ request()->fullUrlWithQuery(['export' => $format]) }}" class="btn btn-outline-secondary" data-export="{{ $format }}">{{ $label }}</a>
            @endforeach
        @endif
    </x-slot:actions>

    <form method="GET" action="{{ route('accounting.reports.show', $key) }}" class="d-flex flex-wrap gap-2 align-items-end mb-3" data-report-filter>
        @if ($key === 'ledger')
            <x-form.select name="account" :label="__('Account')" :options="$accounts" :value="$account" :placeholder="__('Choose an account')" wrapper-class="mb-0" />
        @endif
        @if ($rangeless)
            <x-form.input name="as_of" type="date" :label="__('As at')" :value="$filter->to" wrapper-class="mb-0" />
        @else
            <x-form.input name="from" type="date" :label="__('From')" :value="$filter->from" wrapper-class="mb-0" />
            <x-form.input name="to" type="date" :label="__('To')" :value="$filter->to" wrapper-class="mb-0" />
        @endif
        <x-form.select name="property" :label="__('Property')" :options="['none' => __('Not by property')] + $properties" :value="$filter->property" :placeholder="__('All properties')" :search="false" wrapper-class="mb-0" />
        @if ($key === 'ledger')
            <x-form.select name="department" :label="__('Department')" :options="$departments" :value="$filter->departmentId" :placeholder="__('Any')" :search="false" wrapper-class="mb-0" />
        @endif
        <button type="submit" class="btn btn-outline-primary">{{ __('Show') }}</button>
    </form>

    @if ($report)
        <x-card body-class="p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" data-report-table="{{ $key }}">
                    <thead><tr>@foreach ($report->columns as $index => $column)<th class="{{ $index === 0 ? 'ps-3' : ($key === 'ledger' && $index < 3 ? '' : 'text-end') }}">{{ $column }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($report->rows as $row)
                            <tr class="{{ ($row['bold'] ?? false) ? 'fw-semibold' : '' }}">
                                @foreach ($row['cells'] as $index => $cell)
                                    <td class="{{ $index === 0 ? 'ps-3' : ($key === 'ledger' && $index < 3 ? '' : 'text-end font-monospace') }}" @if ($index === 0 && ($row['indent'] ?? 0) > 0) style="padding-left: {{ 1 + $row['indent'] * 1.25 }}rem" @endif>
                                        @if (isset($row['links'][$index]) && $cell !== '')<a href="{{ $row['links'][$index] }}" data-drill>{{ $cell }}</a>@else{{ $cell }}@endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
        @foreach ($report->notes as $note)
            <p class="text-body-secondary small" data-note>{{ $note }}</p>
        @endforeach
    @else
        <x-empty-state :title="__('Choose an account to see its ledger')" />
    @endif
</x-layouts::app>
