@php($tenant = app(\App\Support\Tenancy\TenantContext::class)->tenantOrFail())
@php($money = fn (string|float $value): string => number_format((float) $value, 2))
<x-layouts::app :title="__('Dashboard')" :subtitle="$consolidated ? __('All properties') : implode(', ', $scope['names'])" :breadcrumbs="[__('Dashboard') => null]">
    @if ($canConsolidate)
        <x-slot:actions>
            <div class="btn-group btn-group-sm" role="group" data-dashboard-scope>
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary {{ $all ? '' : 'active' }}">{{ __('This property') }}</a>
                <a href="{{ route('dashboard', ['property' => 'all']) }}" class="btn btn-outline-secondary {{ $all ? 'active' : '' }}" data-all-properties>{{ __('All properties') }}</a>
            </div>
        </x-slot:actions>
    @endif

    @if ($stats === null && $desk === null && $cash === null)
        <x-card>
            <x-empty-state icon="bi-speedometer2" :title="__('Welcome to :name', ['name' => $tenant->name])" :message="__('You are signed in as :name. Use the menu on the left to get to your work.', ['name' => auth()->user()?->getAttribute('name')])">
                <x-status-badge :status="$tenant->status" />
            </x-empty-state>
        </x-card>
    @endif

    @if ($stats !== null)
        <p class="text-body-secondary small mb-2" data-stats-date>
            @if ($stats['date']) {{ __('Night audit figures for :date.', ['date' => \Carbon\CarbonImmutable::parse($stats['date'])->format('D d M Y')]) }} @else {{ __('No night audit has run yet, so there are no figures.') }} @endif
        </p>
        <div class="row" data-kpis>
            <div class="col-6 col-lg-3"><x-stat-box :value="$stats['day']->occupancy.'%'" :label="__('Occupancy')" icon="bi-house-check" color="success" data-kpi="occupancy" /></div>
            <div class="col-6 col-lg-3"><x-stat-box :value="$money($stats['day']->adr)" :label="__('ADR (average daily rate)')" icon="bi-tag" color="primary" data-kpi="adr" /></div>
            <div class="col-6 col-lg-3"><x-stat-box :value="$money($stats['day']->revpar)" :label="__('RevPAR')" icon="bi-graph-up-arrow" color="info" data-kpi="revpar" /></div>
            <div class="col-6 col-lg-3"><x-stat-box :value="$money($stats['day']->roomRevenue)" :label="__('Room revenue')" icon="bi-cash-coin" color="warning" data-kpi="room-revenue" /></div>
        </div>
    @endif

    @if ($desk !== null || $fnb)
        <div class="row" data-desk>
            @if ($desk !== null)
                <div class="col-6 col-lg-3"><x-stat-box :value="$desk['arrivals']" :label="__('Arrivals today')" icon="bi-box-arrow-in-right" color="secondary" :url="route('frontoffice.desk')" data-kpi="arrivals" /></div>
                <div class="col-6 col-lg-3"><x-stat-box :value="$desk['departures']" :label="__('Departures today')" icon="bi-box-arrow-right" color="secondary" data-kpi="departures" /></div>
                <div class="col-6 col-lg-3"><x-stat-box :value="$desk['in_house']" :label="__('In house')" icon="bi-people" color="secondary" data-kpi="in-house" /></div>
                <div class="col-6 col-lg-3"><x-stat-box :value="$desk['deposits']" :label="__('Deposits to collect (:amount)', ['amount' => $money($desk['deposits_due'])])" icon="bi-hourglass-split" color="danger" data-kpi="deposits" /></div>
            @endif
        </div>
    @endif

    @if ($stats !== null)
        <div class="row">
            <div class="col-lg-8">
                <x-card :title="__('Period figures')" icon="bi-table" body-class="p-0">
                    <table class="table table-sm mb-0" data-periods>
                        <thead><tr><th class="ps-3">{{ __('Period') }}</th><th class="text-end">{{ __('Occupancy') }}</th><th class="text-end">{{ __('ADR') }}</th><th class="text-end">{{ __('RevPAR') }}</th><th class="text-end">{{ __('Room revenue') }}</th>@if ($fnb)<th class="text-end pe-3">{{ __('F&B sales') }}</th>@endif</tr></thead>
                        <tbody>
                            @foreach (['day' => __('Last night'), 'month' => __('Month to date'), 'year' => __('Year to date')] as $key => $label)
                                <tr data-period="{{ $key }}">
                                    <td class="ps-3">{{ $label }}</td>
                                    <td class="text-end font-monospace">{{ $stats[$key]->occupancy }}%</td>
                                    <td class="text-end font-monospace">{{ $money($stats[$key]->adr) }}</td>
                                    <td class="text-end font-monospace">{{ $money($stats[$key]->revpar) }}</td>
                                    <td class="text-end font-monospace">{{ $money($stats[$key]->roomRevenue) }}</td>
                                    @if ($fnb)<td class="text-end font-monospace pe-3">{{ $money($stats[$key]->fnbSales) }}</td>@endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            </div>
            <div class="col-lg-4">
                @if ($cash !== null)
                    <x-card :title="__('Cash position')" icon="bi-bank" body-class="p-0">
                        <table class="table table-sm mb-0" data-cash>
                            <tbody>
                                @forelse ($cash['accounts'] as $account)
                                    <tr><td class="ps-3">{{ $account['name'] }}</td><td class="text-end font-monospace pe-3">{{ $money($account['balance']) }}</td></tr>
                                @empty
                                    <tr><td class="ps-3 text-body-secondary">{{ __('No cash or bank accounts yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot><tr class="fw-semibold"><td class="ps-3">{{ __('Total') }}</td><td class="text-end font-monospace pe-3" data-cash-total>{{ $money($cash['total']) }}</td></tr></tfoot>
                        </table>
                    </x-card>
                @endif
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <x-card :title="__('Last 30 days: occupancy and room revenue')" icon="bi-graph-up">
                    <div style="height: 260px" x-data="chart(@js(['type' => 'bar', 'data' => ['labels' => $stats['trend']['labels'], 'datasets' => [
                        ['type' => 'line', 'label' => __('Occupancy %'), 'data' => $stats['trend']['occupancy'], 'yAxisID' => 'y1', 'borderColor' => '#198754', 'tension' => 0.25],
                        ['type' => 'bar', 'label' => __('Room revenue'), 'data' => $stats['trend']['revenue'], 'backgroundColor' => 'rgba(13,110,253,.35)'],
                    ]], 'options' => ['scales' => ['y1' => ['position' => 'right', 'min' => 0, 'max' => 100, 'grid' => ['drawOnChartArea' => false]]]]]))" data-chart="trend"><canvas x-ref="canvas"></canvas></div>
                </x-card>
            </div>
            <div class="col-lg-6">
                <x-card :title="__('Last 12 months: room and F&B revenue')" icon="bi-bar-chart">
                    <div style="height: 260px" x-data="chart(@js(['type' => 'bar', 'data' => ['labels' => $stats['months']['labels'], 'datasets' => array_values(array_filter([
                        ['label' => __('Rooms'), 'data' => $stats['months']['rooms'], 'backgroundColor' => 'rgba(13,110,253,.55)'],
                        $fnb ? ['label' => __('F&B'), 'data' => $stats['months']['fnb'], 'backgroundColor' => 'rgba(255,193,7,.65)'] : null,
                    ]))], 'options' => ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true]]]]))" data-chart="months"><canvas x-ref="canvas"></canvas></div>
                </x-card>
            </div>
        </div>
    @endif

    @if ($stats === null && $cash !== null)
        <x-card :title="__('Cash position')" icon="bi-bank" body-class="p-0">
            <table class="table table-sm mb-0" data-cash>
                <tbody>
                    @foreach ($cash['accounts'] as $account)
                        <tr><td class="ps-3">{{ $account['name'] }}</td><td class="text-end font-monospace pe-3">{{ $money($account['balance']) }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="fw-semibold"><td class="ps-3">{{ __('Total') }}</td><td class="text-end font-monospace pe-3" data-cash-total>{{ $money($cash['total']) }}</td></tr></tfoot>
            </table>
        </x-card>
    @endif
</x-layouts::app>
