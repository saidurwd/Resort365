<x-layouts::app :title="$report->title" :subtitle="$report->subtitle" :breadcrumbs="[__('Accounting') => null, __('Budgets') => route('accounting.budgets.index'), $budget->name => route('accounting.budgets.show', $budget), __('Against actual') => null]">
    <x-slot:actions>
        @if ($canExport)
            @foreach (['xlsx' => 'Excel', 'csv' => 'CSV', 'pdf' => 'PDF'] as $format => $label)
                <a href="{{ request()->fullUrlWithQuery(['export' => $format]) }}" class="btn btn-outline-secondary btn-sm" data-export="{{ $format }}">{{ $label }}</a>
            @endforeach
        @endif
    </x-slot:actions>

    <form method="GET" action="{{ route('accounting.budgets.report', $budget) }}" class="d-flex flex-wrap gap-2 align-items-end mb-3" data-report-filter>
        <x-form.select name="view" :label="__('Show')" :options="['accounts' => __('By account'), 'months' => __('By month')]" :value="$view" :search="false" wrapper-class="mb-0" />
        @if ($view === 'accounts')
            <x-form.select name="from" :label="__('From month')" :options="$months" :value="$from" :search="false" wrapper-class="mb-0" />
            <x-form.select name="to" :label="__('To month')" :options="$months" :value="$to" :search="false" wrapper-class="mb-0" />
        @else
            <x-form.select name="account" :label="__('Account')" :options="$accounts" :value="$account" :placeholder="__('All income and expense')" wrapper-class="mb-0" />
        @endif
        <x-form.select name="department" :label="__('Department')" :options="$departments" :value="$department" :placeholder="__('All departments')" :search="false" wrapper-class="mb-0" />
        <button type="submit" class="btn btn-outline-primary">{{ __('Show') }}</button>
    </form>

    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-budget-vs-actual="{{ $view }}">
                <thead><tr>@foreach ($report->columns as $index => $column)<th class="{{ $index === 0 ? 'ps-3' : 'text-end' }}">{{ $column }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($report->rows as $row)
                        <tr class="{{ ($row['bold'] ?? false) ? 'fw-semibold' : '' }}" data-row>
                            @foreach ($row['cells'] as $index => $cell)
                                @php($negative = $index > 0 && str_starts_with($cell, '-') && ! in_array($report->columns[$index] ?? '', [__('Budget'), __('Actual'), __('Income budget'), __('Income actual'), __('Expense budget'), __('Expense actual'), __('Net budget'), __('Net actual')], true))
                                <td class="{{ $index === 0 ? (($row['indent'] ?? 0) > 0 ? 'ps-5' : 'ps-3') : 'text-end font-monospace' }} {{ $negative ? 'text-danger' : '' }}">{{ $cell }}</td>
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
</x-layouts::app>
