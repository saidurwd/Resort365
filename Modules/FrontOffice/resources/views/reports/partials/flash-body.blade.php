@php
    $money = fn ($amount): string => number_format((float) $amount, 2);
    $s = $report['stats'];
    $t = $report['takings'];
@endphp
<div data-flash-report="{{ $date->toDateString() }}" @if ($report['provisional']) data-provisional @endif>
    @if ($report['provisional'])
        <div class="alert alert-info py-2">{{ __('Provisional: this business date is still open. The figures are final after the night audit.') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat-box :label="__('Occupancy')" :value="$s['occupancy_percent'].'%'" icon="bi-door-closed" color="primary" /></div>
        <div class="col-6 col-lg-3"><x-stat-box :label="__('ADR')" :value="$money($s['adr'])" icon="bi-tag" color="success" /></div>
        <div class="col-6 col-lg-3"><x-stat-box :label="__('RevPAR')" :value="$money($s['revpar'])" icon="bi-graph-up" color="info" /></div>
        <div class="col-6 col-lg-3"><x-stat-box :label="__('Room revenue')" :value="$money($s['room_revenue'])" icon="bi-cash-stack" color="warning" /></div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <x-card :title="__('Rooms')" icon="bi-door-open" body-class="p-0">
                <table class="table table-sm mb-0" data-flash-rooms>
                    <tr><td class="ps-3">{{ __('Rooms in the property') }}</td><td class="text-end pe-3">{{ $s['rooms_total'] }}</td></tr>
                    <tr><td class="ps-3">{{ __('Out of order') }}</td><td class="text-end pe-3">{{ $s['rooms_out_of_order'] }}</td></tr>
                    <tr><td class="ps-3">{{ __('Available to sell') }}</td><td class="text-end pe-3">{{ $s['rooms_available'] }}</td></tr>
                    <tr><td class="ps-3">{{ __('Blocked (owner, holds)') }}</td><td class="text-end pe-3">{{ $s['rooms_blocked'] }}</td></tr>
                    <tr class="fw-semibold"><td class="ps-3">{{ __('Occupied') }}</td><td class="text-end pe-3" data-occupied>{{ $s['rooms_occupied'] }}</td></tr>
                    <tr><td class="ps-3">{{ __('Guests in house') }}</td><td class="text-end pe-3">{{ trans_choice(':count adult|:count adults', $s['adults']) }}, {{ trans_choice(':count child|:count children', $s['children']) }}</td></tr>
                    <tr><td class="ps-3">{{ __('Arrivals / departures / no-shows') }}</td><td class="text-end pe-3">{{ $s['arrivals'] }} / {{ $s['departures'] }} / {{ $s['no_shows'] }}</td></tr>
                </table>
            </x-card>
            <x-card :title="__('Revenue of the night')" icon="bi-moon" body-class="p-0">
                <table class="table table-sm mb-0">
                    <tr><td class="ps-3">{{ __('Room revenue') }}</td><td class="text-end pe-3 font-monospace">{{ $money($s['room_revenue']) }}</td></tr>
                    <tr><td class="ps-3">{{ __('F&B in package rates') }}</td><td class="text-end pe-3 font-monospace">{{ $money($s['package_meal_revenue']) }}</td></tr>
                    <tr><td class="ps-3">{{ __('Taxes on rooms') }}</td><td class="text-end pe-3 font-monospace">{{ $money($s['room_tax']) }}</td></tr>
                    <tr><td class="ps-3">{{ __('Restaurant covers / sales') }}</td><td class="text-end pe-3 text-body-secondary">{{ $s['fnb_covers'] }} / {{ $money($s['fnb_sales']) }}</td></tr>
                </table>
            </x-card>
        </div>
        <div class="col-lg-6">
            <x-card :title="__('Charges posted')" icon="bi-receipt" body-class="p-0">
                <table class="table table-sm mb-0" data-flash-charges>
                    <thead><tr><th class="ps-3">{{ __('Category') }}</th><th class="text-end">{{ __('Net') }}</th><th class="text-end">{{ __('Tax') }}</th><th class="text-end pe-3">{{ __('Total') }}</th></tr></thead>
                    <tbody>
                        @forelse ($t['charges'] as $row)
                            <tr><td class="ps-3">{{ $row['label'] }}</td><td class="text-end font-monospace">{{ $money($row['net']) }}</td><td class="text-end font-monospace">{{ $money($row['tax']) }}</td><td class="text-end pe-3 font-monospace">{{ $money($row['total']) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="ps-3 text-body-secondary">{{ __('Nothing posted.') }}</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr class="fw-semibold"><td class="ps-3" colspan="3">{{ __('Total') }}</td><td class="text-end pe-3 font-monospace">{{ $money($t['chargesTotal']) }}</td></tr></tfoot>
                </table>
            </x-card>
            <x-card :title="__('Money taken')" icon="bi-cash-coin" body-class="p-0">
                <table class="table table-sm mb-0" data-flash-payments>
                    <thead><tr><th class="ps-3">{{ __('Method') }}</th><th class="text-end">{{ __('Received') }}</th><th class="text-end pe-3">{{ __('Refunded') }}</th></tr></thead>
                    <tbody>
                        @forelse ($t['payments'] as $row)
                            <tr><td class="ps-3">{{ $row['label'] }}</td><td class="text-end font-monospace">{{ $money($row['received']) }}</td><td class="text-end pe-3 font-monospace">{{ $money($row['refunded']) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="ps-3 text-body-secondary">{{ __('No payments.') }}</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold"><td class="ps-3">{{ __('Total') }}</td><td class="text-end font-monospace">{{ $money($t['receivedTotal']) }}</td><td class="text-end pe-3 font-monospace">{{ $money($t['refundedTotal']) }}</td></tr>
                        <tr><td class="ps-3" colspan="2">{{ __('Security deposits held') }}</td><td class="text-end pe-3 font-monospace">{{ $money($t['securityDeposits']) }}</td></tr>
                    </tfoot>
                </table>
            </x-card>
        </div>
    </div>
</div>
