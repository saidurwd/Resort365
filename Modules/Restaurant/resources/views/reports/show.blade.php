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
        @if ($report === 'trends')
            <x-form.select name="grain" :label="__('By')" :options="['day' => __('Day'), 'week' => __('Week'), 'month' => __('Month')]" :value="$grain" :search="false" wrapper-class="mb-0" />
        @endif
        @if ($report === 'menu-engineering')
            <x-form.select name="category" :label="__('Category')" :options="$categories" :value="$category" :placeholder="__('All categories')" :search="false" wrapper-class="mb-0" />
        @endif
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

    @if ($chart)
        <x-card :title="$report === 'menu-engineering' ? __('Popularity and margin') : __('Sales and spend per cover')" icon="bi-graph-up">
            <div class="chart-box" x-data="chart(@js($chart))" data-chart="{{ $report }}"><canvas x-ref="canvas"></canvas></div>
        </x-card>
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
    @if ($report === 'menu-engineering')
        <div class="row g-2 mt-1" data-class-advice>
            @foreach (\Modules\Restaurant\Enums\MenuClass::cases() as $class)
                <div class="col-md-6 col-xl-3"><div class="border rounded p-2 h-100"><span class="badge text-bg-{{ $class->color() }}">{{ $class->label() }}</span> <span class="small">{{ $class->advice() }}</span></div></div>
            @endforeach
        </div>
        @if ($unknown !== [])
            <x-card :title="__('Dishes without a recipe')" icon="bi-question-circle" class="mt-3" body-class="p-0">
                <table class="table table-sm mb-0" data-unknown>
                    <thead><tr><th class="ps-3">{{ __('Dish') }}</th><th>{{ __('Category') }}</th><th class="text-end">{{ __('Sold') }}</th><th class="text-end pe-3">{{ __('Revenue') }}</th></tr></thead>
                    <tbody>@foreach ($unknown as $dish)<tr data-unknown-dish="{{ $dish['id'] }}"><td class="ps-3">{{ $dish['name'] }}</td><td>{{ $dish['category'] }}</td><td class="text-end">{{ $dish['quantity'] }}</td><td class="text-end font-monospace pe-3">{{ number_format((float) $dish['revenue'], 2) }}</td></tr>@endforeach</tbody>
                </table>
            </x-card>
        @endif
    @endif
</x-layouts::app>
