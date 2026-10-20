<?php

/*
| What Reports reads from the other modules (Step 7.1): the night audit statistics, bookings, the forecast, stays per guest,
| nationalities, company names and the cash position.
*/

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Contracts\CashPosition;
use Modules\Accounting\Models\BankAccount;
use Modules\FrontOffice\Contracts\StatisticsLookup;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\Models\Company;
use Modules\Guest\Models\Guest;
use Modules\Reservation\Contracts\ReservationAnalytics;
use Modules\Reservation\Enums\LockType;

require_once __DIR__.'/../Support/reports-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

it('reads the night audit days, adds them up, and finds the latest', function (): void {
    rpStat('2026-03-01', ['rooms_available' => 10, 'rooms_occupied' => 5, 'room_revenue' => '30000.00', 'fnb_sales' => '5000.00', 'arrivals' => 2]);
    rpStat('2026-03-02', ['rooms_available' => 10, 'rooms_occupied' => 8, 'room_revenue' => '52000.00', 'fnb_sales' => '7000.00', 'arrivals' => 4]);
    rpStat('2026-04-01', ['rooms_available' => 10, 'rooms_occupied' => 1, 'room_revenue' => '6000.00']);
    $property = bookingIds()['property'];
    $stats = app(StatisticsLookup::class);

    $days = booking(fn (): array => $stats->between([$property], '2026-03-01', '2026-03-31'));
    $totals = booking(fn () => $stats->totals([$property], '2026-03-01', '2026-03-31'));

    expect($days)->toHaveCount(2)->and($days[0]->date)->toBe('2026-03-01')->and($days[1]->roomsOccupied)->toBe(8)->and($totals->days)->toBe(2)->and($totals->roomsAvailable)->toBe(20)->and($totals->roomsOccupied)->toBe(13)
        ->and($totals->roomRevenue)->toBe('82000.00')->and($totals->fnbSales)->toBe('12000.00')->and($totals->arrivals)->toBe(6)
        ->and(booking(fn (): ?string => $stats->latestDate([$property])))->toBe('2026-04-01')
        ->and(booking(fn () => $stats->totals([$property + 99], '2026-03-01', '2026-03-31'))->days)->toBe(0)->and(booking(fn (): ?string => $stats->latestDate([$property + 99])))->toBeNull();
});

it('reads bookings by the day they were made in the property\'s time zone, or by arrival', function (): void {
    // 20:00 UTC on 9 March is 02:00 on 10 March in Dhaka.
    $a = rpBook(['source' => 'online', 'status' => 'checked_out', 'created_at' => '2026-03-09 20:00:00', 'grand_total' => '20000.00', 'company_id' => null], rooms: 2);
    $b = rpBook(['source' => 'phone', 'status' => 'cancelled', 'check_in' => '2026-04-02', 'check_out' => '2026-04-04', 'created_at' => '2026-03-10 10:00:00', 'cancelled_at' => '2026-03-12 09:00:00', 'cancellation_fee' => '500.00']);
    $property = bookingIds()['property'];
    $analytics = app(ReservationAnalytics::class);

    $byArrival = booking(fn (): array => $analytics->bookings([$property], 'arrival', '2026-03-01', '2026-03-31'));
    $madeOn = booking(fn (): array => $analytics->bookings([$property], 'booked', '2026-03-10', '2026-03-10'));
    $before = booking(fn (): array => $analytics->bookings([$property], 'booked', '2026-03-09', '2026-03-09'));

    expect($byArrival)->toHaveCount(1)->and($byArrival[0]->reservationId)->toBe($a->id)->and($byArrival[0]->rooms)->toBe(2)->and($byArrival[0]->nights)->toBe(2)->and($byArrival[0]->roomNights())->toBe(4)->and($byArrival[0]->bookedOn)->toBe('2026-03-10')
        ->and($byArrival[0]->leadDays())->toBe(0)->and($byArrival[0]->total)->toBe('20000.00')->and($byArrival[0]->source)->toBe('online')
        ->and($madeOn)->toHaveCount(2)->and($madeOn[1]->reservationId)->toBe($b->id)->and($madeOn[1]->isCancelled())->toBeTrue()->and($madeOn[1]->cancelledOn)->toBe('2026-03-12')->and($madeOn[1]->cancellationFee)->toBe('500.00')->and($before)->toBe([]);
});

it('counts the rooms to sell, blocked and booked for each night, and the bookings arriving', function (): void {
    rpLock('701', '2026-04-01', LockType::Reservation);
    rpLock('702', '2026-04-01', LockType::Reservation);
    rpLock('703', '2026-04-01', LockType::Hold);
    rpLock('801', '2026-04-01', LockType::OutOfOrder);
    rpLock('701', '2026-04-02', LockType::OwnerBlock);
    rpBook(['status' => 'tentative', 'check_in' => '2026-04-01', 'check_out' => '2026-04-03']);
    rpBook(['status' => 'cancelled', 'check_in' => '2026-04-01', 'check_out' => '2026-04-03']);
    $days = booking(fn (): array => app(ReservationAnalytics::class)->forecast([bookingIds()['property']], '2026-04-01', '2026-04-03'));

    expect($days)->toHaveCount(3)->and([$days[0]->roomsTotal, $days[0]->roomsBlocked, $days[0]->roomsBooked, $days[0]->arrivals])->toBe([8, 1, 3, 1])
        ->and([$days[1]->roomsBlocked, $days[1]->roomsBooked, $days[1]->arrivals])->toBe([1, 0, 0])->and([$days[2]->roomsBlocked, $days[2]->roomsBooked])->toBe([0, 0]);
});

it('counts the stays each guest has had, checked in or out only', function (): void {
    $guest = bookingIds()['guest'];
    rpBook(['status' => 'checked_out']);
    rpBook(['status' => 'checked_in', 'check_in' => '2026-05-01', 'check_out' => '2026-05-03']);
    rpBook(['status' => 'tentative', 'check_in' => '2026-06-01', 'check_out' => '2026-06-03']);
    rpBook(['status' => 'cancelled', 'check_in' => '2026-07-01', 'check_out' => '2026-07-03']);
    $analytics = app(ReservationAnalytics::class);

    expect(booking(fn (): array => $analytics->stayCounts([$guest, 999999])))->toBe([$guest => 2])->and(booking(fn (): array => $analytics->stayCounts([])))->toBe([]);
});

it('answers guests\' nationalities and companies\' names', function (): void {
    rpCountry();
    [$a, $b, $company] = booking(fn (): array => [
        Guest::factory()->create(['nationality_code' => 'GB'])->id, Guest::factory()->create(['nationality_code' => null])->id, Company::factory()->create(['name' => 'Dhaka Bank'])->id,
    ]);
    $guests = app(GuestLookup::class);

    expect(booking(fn (): array => $guests->nationalities([$a, $b])))->toBe([$a => 'GB', $b => null])->and(booking(fn (): array => $guests->companyNames([$company, 999999])))->toBe([$company => 'Dhaka Bank']);
});

it('lists the active cash and bank accounts with their ledger balance', function (): void {
    booking(function (): void {
        BankAccount::factory()->create(['name' => 'City Bank current']);
        BankAccount::factory()->create(['name' => 'Old account', 'is_active' => false]);
    });
    $balances = booking(fn (): array => app(CashPosition::class)->balances('2026-03-31'));

    expect($balances)->toHaveCount(1)->and($balances[0]->name)->toBe('City Bank current')->and($balances[0]->kind)->toBe('bank')->and($balances[0]->balance)->toBe('0.00');
});
