<x-layouts::app :title="__('Front desk')" :subtitle="$property->name.' · '.\Carbon\Carbon::parse($property->businessDate)->format('l, d M Y')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Front desk') => null]">
    @can('reservation.booking.create')
        <x-slot:actions>
            <a href="{{ route('reservation.bookings.create', ['walk_in' => 1]) }}" class="btn btn-sm btn-primary" data-walk-in><i class="bi bi-person-walking"></i> {{ __('Walk-in') }}</a>
        </x-slot:actions>
    @endcan

    <div class="row" data-occupancy>
        <div class="col-6 col-lg-3"><x-stat-box :value="$occupancy['percent'].'%'" :label="__('Occupancy tonight (:booked of :rooms rooms)', ['booked' => $occupancy['booked'], 'rooms' => $occupancy['rooms']])" icon="bi-pie-chart" color="primary" /></div>
        <div class="col-6 col-lg-3"><x-stat-box :value="count($arrivals)" :label="__('Arrivals')" icon="bi-box-arrow-in-right" color="success" /></div>
        <div class="col-6 col-lg-3"><x-stat-box :value="count($departures)" :label="__('Departures')" icon="bi-box-arrow-right" color="warning" /></div>
        <div class="col-6 col-lg-3"><x-stat-box :value="count($inHouse)" :label="__('In house')" icon="bi-house-check" color="info" /></div>
    </div>

    <div class="row">
        <div class="col-xl-6">
            <x-card :title="__('Arrivals today')" icon="bi-box-arrow-in-right" body-class="p-0">
                @include('frontoffice::desk.partials.list', ['rows' => $arrivals, 'list' => 'arrivals', 'empty' => __('No more arrivals today.'), 'action' => 'check-in'])
            </x-card>
            <x-card :title="__('Departures today')" icon="bi-box-arrow-right" body-class="p-0">
                @include('frontoffice::desk.partials.list', ['rows' => $departures, 'list' => 'departures', 'empty' => __('No departures today.'), 'action' => null])
            </x-card>
        </div>
        <div class="col-xl-6">
            <x-card :title="__('In house')" icon="bi-house-check" body-class="p-0">
                @include('frontoffice::desk.partials.list', ['rows' => $inHouse, 'list' => 'in-house', 'empty' => __('Nobody is checked in.'), 'action' => null])
            </x-card>
            @if ($vips !== [])
                <x-card :title="__('VIPs')" icon="bi-star" body-class="p-0">
                    @include('frontoffice::desk.partials.list', ['rows' => $vips, 'list' => 'vips', 'empty' => '', 'action' => null])
                </x-card>
            @endif
            <x-card :title="__('Waiting for a deposit')" icon="bi-hourglass-split" body-class="p-0">
                @if ($pending === [])
                    <div class="p-3 text-body-secondary small">{{ __('No deposits pending.') }}</div>
                @else
                    <table class="table table-sm align-middle mb-0" data-list="pending">
                        <tbody>
                            @foreach ($pending as $row)
                                <tr>
                                    <td class="ps-3"><a href="{{ route('reservation.bookings.show', $row->id) }}#payments">{{ $row->guestName }}</a> <span class="small text-body-secondary">{{ $row->code }}</span></td>
                                    <td class="text-end font-monospace">{{ number_format((float) bcsub($row->depositRequired, $row->amountPaid, 2), 2) }}</td>
                                    <td class="pe-3 small text-nowrap">{{ $row->depositDueAt ? __('due :time', ['time' => \Carbon\Carbon::parse($row->depositDueAt)->setTimezone($property->timezone)->format('d M H:i')]) : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts::app>
