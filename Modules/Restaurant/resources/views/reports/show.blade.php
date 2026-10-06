<x-layouts::app :title="$data['title']" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('Reports') => null]">
    <ul class="nav nav-tabs mb-3" data-report-tabs>
        @foreach ($tabs as $key => $label)
            <li class="nav-item"><a class="nav-link @if ($key === $report) active @endif" href="{{ route('restaurant.reports.'.$key, ['from' => $from, 'to' => $to, 'outlet' => $outletId]) }}" data-report-tab="{{ $key }}">{{ $label }}</a></li>
        @endforeach
    </ul>

    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end mb-3" data-report-filter>
        <x-form.input name="from" type="date" :label="__('From')" :value="$from" wrapper-class="mb-0" />
        <x-form.input name="to" type="date" :label="__('To')" :value="$to" wrapper-class="mb-0" />
        <x-form.select name="outlet" :label="__('Outlet')" :options="$outlets->pluck('name', 'id')->all()" :value="$outletId" :placeholder="__('All outlets')" :search="false" wrapper-class="mb-0" />
        @if ($report === 'sales')
            <x-form.select name="by" :label="__('Group by')" :options="$saleBy" :value="$by" :search="false" wrapper-class="mb-0" />
        @endif
        <button type="submit" class="btn btn-primary">{{ __('Show') }}</button>
        <a class="btn btn-outline-secondary" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" data-export-csv><i class="bi bi-download"></i> {{ __('CSV') }}</a>
    </form>

    @if ($data['summary'] !== [])
        <div class="row g-2 mb-3" data-report-summary>
            @foreach ($data['summary'] as $figure)
                <div class="col-6 col-md-3 col-xl-2"><div class="border rounded p-2 h-100"><div class="small text-body-secondary">{{ $figure['label'] }}</div><div class="fs-5 fw-semibold font-monospace" data-figure="{{ $figure['label'] }}">{{ $figure['value'] }}</div></div></div>
            @endforeach
        </div>
    @endif

    <x-card :title="$data['title']" icon="bi-bar-chart" body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-report-table="{{ $report }}">
                <thead><tr>@foreach ($data['columns'] as $column)<th class="@if ($loop->first) ps-3 @endif">{{ $column }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse ($data['rows'] as $row)
                        <tr>@foreach ($row as $cell)<td class="@if ($loop->first) ps-3 @endif @if (is_numeric(str_replace([',', '%'], '', $cell)) && ! $loop->first) text-end font-monospace @endif">{{ $cell }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($data['columns']) }}"><x-empty-state icon="bi-bar-chart" :title="__('Nothing to report')" :message="__('No records in this range.')" /></td></tr>
                    @endforelse
                </tbody>
                @if ($data['totals'] !== null && $data['rows'] !== [])
                    <tfoot><tr class="fw-bold">@foreach ($data['totals'] as $cell)<td class="@if ($loop->first) ps-3 @else text-end font-monospace @endif">{{ $cell }}</td>@endforeach</tr></tfoot>
                @endif
            </table>
        </div>
    </x-card>
    @if ($data['note'])<p class="small text-body-secondary mt-2">{{ $data['note'] }}</p>@endif
</x-layouts::app>
