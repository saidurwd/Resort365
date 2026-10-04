<?php

use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Services\BookingSourceReport;

it('sums bookings, cancellations, nights and revenue per source, largest revenue first', function (): void {
    $report = new BookingSourceReport()->summarise([
        ['source' => ReservationSource::Phone, 'cancelled' => false, 'nights' => 3, 'total' => '22770.00'],
        ['source' => ReservationSource::Online, 'cancelled' => false, 'nights' => 2, 'total' => '30000.00'],
        ['source' => ReservationSource::Phone, 'cancelled' => false, 'nights' => 2, 'total' => '15180.00'],
        ['source' => ReservationSource::Phone, 'cancelled' => true, 'nights' => 5, 'total' => '99999.00'],
        ['source' => ReservationSource::Online, 'cancelled' => false, 'nights' => 1, 'total' => '0.01'],
    ]);

    expect(array_map(fn (array $row): array => [$row['source'], $row['bookings'], $row['cancelled'], $row['nights'], $row['revenue'], $row['average'], $row['share']], $report['rows']))->toBe([
        [ReservationSource::Phone, 3, 1, 5, '37950.00', '18975.00', '55.8'],
        [ReservationSource::Online, 2, 0, 3, '30000.01', '15000.01', '44.2'],
    ])->and($report['totals'])->toBe(['bookings' => 5, 'cancelled' => 1, 'nights' => 8, 'revenue' => '67950.01', 'average' => '16987.50']);
});

it('handles a period without bookings or with only cancellations', function (): void {
    $service = new BookingSourceReport;

    expect($service->summarise([]))->toBe(['rows' => [], 'totals' => ['bookings' => 0, 'cancelled' => 0, 'nights' => 0, 'revenue' => '0.00', 'average' => '0.00']])
        ->and($service->summarise([['source' => ReservationSource::Email, 'cancelled' => true, 'nights' => 2, 'total' => '100.00']])['rows'][0])
        ->toMatchArray(['bookings' => 1, 'cancelled' => 1, 'revenue' => '0.00', 'average' => '0.00', 'share' => '0.0']);
});
