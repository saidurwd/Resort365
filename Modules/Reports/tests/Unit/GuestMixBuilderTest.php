<?php

/*
| The guest reports (Step 7.1): nationality mix, repeat guests, and the top guests and companies. Only stays count. Pure.
*/
use Modules\Reports\Services\Calculation\GuestMixBuilder;
use Modules\Reservation\DTOs\BookingFact;
use Tests\TestCase;

require_once __DIR__.'/../Support/reports-fixtures.php';

// Labels go through the translator, so the application is booted (no database).
uses(TestCase::class);

/**
 * @return list<BookingFact>
 */
function gmStays(): array
{
    return [
        rpFact(['guestId' => 1, 'total' => '10000.00', 'companyId' => 7]),
        rpFact(['guestId' => 1, 'total' => '30000.00', 'nights' => 3, 'checkIn' => '2026-03-20']),
        rpFact(['guestId' => 2, 'total' => '20000.00', 'rooms' => 2, 'status' => 'checked_in', 'companyId' => 7]),
        rpFact(['guestId' => 3, 'total' => '5000.00', 'companyId' => 8]),
        rpFact(['guestId' => 4, 'total' => '99999.00', 'status' => 'cancelled']),
        rpFact(['guestId' => 5, 'total' => '88888.00', 'status' => 'no_show']),
    ];
}

it('shows the nationality mix by room nights, counting each guest once', function (): void {
    $table = (new GuestMixBuilder)->nationalities(gmStays(), [1 => 'BD', 2 => 'GB', 3 => null, 4 => 'US'], ['BD' => 'Bangladesh', 'GB' => 'United Kingdom'], 'Nationalities');

    // Room nights: guest 1: 2 + 3 = 5; guest 2: 2 × 2 = 4; guest 3: 2. Cancelled and no-show bookings are left out.
    expect($table->rows)->toBe([['Bangladesh', '1', '2', '5', '45.5%'], ['United Kingdom', '1', '1', '4', '36.4%'], ['Not recorded', '1', '1', '2', '18.2%']])
        ->and($table->totals)->toBe(['Total', '3', '4', '11', '100.0%']);
});

it('lists guests who have stayed more than once, the most frequent first', function (): void {
    $table = (new GuestMixBuilder)->repeatGuests(gmStays(), [1 => 5, 2 => 1, 3 => 2], [1 => 'Rahim Uddin', 3 => 'Ayesha Siddique'], 10, 'Repeat');

    expect($table->rows)->toBe([['Rahim Uddin', '2', '5', '5', '2026-03-20'], ['Ayesha Siddique', '1', '2', '2', '2026-03-10']])
        ->and($table->summary[1]['value'])->toBe('2')->and($table->summary[2]['value'])->toBe('66.7%');
});

it('ranks the top guests and companies by what they spent', function (): void {
    $guests = (new GuestMixBuilder)->topGuests(gmStays(), [1 => 'Rahim Uddin', 2 => 'John Smith', 3 => 'Ayesha Siddique'], 2, 'Top guests');
    $companies = (new GuestMixBuilder)->topCompanies(gmStays(), [7 => 'Dhaka Bank', 8 => 'Bay Traders'], 5, 'Top companies');

    expect($guests->rows)->toBe([['Rahim Uddin', '2', '5', '40,000.00'], ['John Smith', '1', '4', '20,000.00']])->and($guests->summary[0]['value'])->toBe('3')
        ->and($companies->rows)->toBe([['Dhaka Bank', '2', '6', '30,000.00'], ['Bay Traders', '1', '2', '5,000.00']]);
});
