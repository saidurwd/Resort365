@php($money = fn (string $amount): string => number_format((float) $amount, 2))

<x-layouts::app :title="__('Booking sources')" :subtitle="app(\App\Support\Tenancy\PropertyContext::class)->currentName()" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Booking sources') => null]">
    <x-card>
        <form method="GET" action="{{ route('reservation.reports.sources') }}" class="row g-2 align-items-end" data-report-filters>
            <x-form.date name="from" :label="__('From')" :value="$from" wrapper-class="col-sm-6 col-lg-3 mb-0" />
            <x-form.date name="to" :label="__('To')" :value="$to" wrapper-class="col-sm-6 col-lg-3 mb-0" />
            <x-form.select name="by" :label="__('Count')" :search="false" :value="$by" wrapper-class="col-sm-6 col-lg-3 mb-0"
                :options="['booked' => __('Bookings made in the period'), 'arrival' => __('Arrivals in the period')]" />
            <div class="col-sm-6 col-lg-3"><button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> {{ __('Show') }}</button></div>
        </form>
    </x-card>

    <x-card :title="__('By source')" icon="bi-diagram-3" body-class="p-0">
        @if ($report['rows'] === [])
            <div class="p-3"><x-empty-state :title="__('No bookings in this period')" icon="bi-calendar-x" /></div>
        @else
            <div class="table-responsive">
                <table class="table mb-0" data-source-report>
                    <thead>
                        <tr>
                            <th class="ps-3">{{ __('Source') }}</th><th class="text-end">{{ __('Bookings') }}</th><th class="text-end">{{ __('Cancelled') }}</th>
                            <th class="text-end">{{ __('Nights') }}</th><th class="text-end">{{ __('Revenue (:currency)', ['currency' => $currency]) }}</th>
                            <th class="text-end">{{ __('Average booking') }}</th><th class="text-end pe-3">{{ __('Share') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['rows'] as $row)
                            <tr data-source="{{ $row['source']->value }}">
                                <td class="ps-3"><x-status-badge :status="$row['source']" /></td>
                                <td class="text-end">{{ $row['bookings'] }}</td>
                                <td class="text-end">{{ $row['cancelled'] }}</td>
                                <td class="text-end">{{ $row['nights'] }}</td>
                                <td class="text-end font-monospace">{{ $money($row['revenue']) }}</td>
                                <td class="text-end font-monospace">{{ $money($row['average']) }}</td>
                                <td class="text-end pe-3">{{ $row['share'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td class="ps-3">{{ __('Total') }}</td><td class="text-end">{{ $report['totals']['bookings'] }}</td><td class="text-end">{{ $report['totals']['cancelled'] }}</td>
                            <td class="text-end">{{ $report['totals']['nights'] }}</td><td class="text-end font-monospace">{{ $money($report['totals']['revenue']) }}</td>
                            <td class="text-end font-monospace">{{ $money($report['totals']['average']) }}</td><td class="pe-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-card>
    <p class="small text-body-secondary">{{ __('Revenue is the booked total including taxes; cancelled bookings count but bring no revenue or nights. A whole cottage counts as one unit per night.') }}</p>
</x-layouts::app>
