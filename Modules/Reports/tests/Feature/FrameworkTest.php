<?php

/*
| The report framework (Step 7.1): the registry, turning a request into a filter (this property, or all of them that share a
| currency), and running every report against real bookings.
*/
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Guest\Models\Guest;
use Modules\Reports\DTOs\ReportFilter;
use Modules\Reports\DTOs\ReportTable;
use Modules\Reports\Services\ReportFilters;
use Modules\Reports\Services\ReportRegistry;
use Modules\Reports\Services\ReportRunner;

require_once __DIR__.'/../Support/reports-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

it('registers the rooms and guest reports with their permissions', function (): void {
    $registry = app(ReportRegistry::class);
    $keys = array_keys($registry->all());

    expect($keys)->toBe(['forecast', 'pace', 'sources', 'cancellations', 'length-of-stay', 'nationalities', 'repeat-guests', 'top-guests', 'top-companies'])
        ->and($registry->find('forecast')->permission)->toBe('reports.rooms.view')->and($registry->find('top-guests')->permission)->toBe('reports.guests.view')->and($registry->find('forecast')->filters)->toBe(['horizon'])
        ->and($registry->find('nope'))->toBeNull();
});

it('turns what was asked into a filter: the dates, the booking basis and the number of rows', function (): void {
    $filters = app(ReportFilters::class);
    $property = bookingIds()['property'];
    $today = booking(fn (): string => $filters->scope('')['today']);

    $range = booking(fn (): ReportFilter => $filters->make(app(ReportRegistry::class)->find('sources'), ['from' => '2026-03-01', 'to' => '2026-03-31', 'by' => 'arrival']));
    $defaults = booking(fn (): ReportFilter => $filters->make(app(ReportRegistry::class)->find('sources'), []));
    $guests = booking(fn (): ReportFilter => $filters->make(app(ReportRegistry::class)->find('top-guests'), ['limit' => '500', 'by' => 'nonsense']));
    $horizon = booking(fn (): ReportFilter => $filters->make(app(ReportRegistry::class)->find('forecast'), ['horizon' => '60']));

    expect($range->propertyIds)->toBe([$property])->and($range->from)->toBe('2026-03-01')->and($range->to)->toBe('2026-03-31')->and($range->by)->toBe('arrival')->and($range->days())->toBe(31)
        ->and($defaults->from)->toBe(substr($today, 0, 8).'01')->and($defaults->to)->toBe($today)->and($defaults->by)->toBe('booked')
        ->and($guests->by)->toBe('arrival')->and($guests->limit)->toBe(100)
        ->and($horizon->from)->toBe($today)->and($horizon->to)->toBe(date('Y-m-d', strtotime($today.' +59 days')))->and($horizon->days())->toBe(60);
});

it('covers every property with the same currency for a consolidated report, and no other', function (): void {
    $other = rpProperty('GVR');
    rpProperty('USA', 'USD');
    $property = bookingIds()['property'];
    $filters = app(ReportFilters::class);

    $single = booking(fn (): array => $filters->scope(''));
    $all = booking(fn (): array => $filters->scope('all'));
    $report = booking(fn (): ReportFilter => $filters->make(app(ReportRegistry::class)->find('sources'), ['property' => 'all']));

    expect($single['ids'])->toBe([$property])->and($single['currency'])->toBe('BDT')->and($all['ids'])->toBe([$property, $other])->and($all['names'])->toHaveCount(2)->and($report->consolidated())->toBeTrue()->and($report->currency)->toBe('BDT');
});

it('runs every report and answers a table', function (): void {
    rpBook(['source' => 'online', 'created_at' => '2026-03-01 10:00:00'], rooms: 2);
    rpBook(['source' => 'phone', 'status' => 'cancelled', 'cancelled_at' => '2026-03-05 10:00:00', 'cancellation_fee' => '300.00']);
    $runner = app(ReportRunner::class);
    $filter = new ReportFilter([bookingIds()['property']], '2026-03-01', '2026-03-31', 'arrival', 10, 'BDT');

    foreach (array_keys(app(ReportRegistry::class)->all()) as $key) {
        $table = booking(fn (): ReportTable => $runner->run($key, $filter));

        expect($table)->toBeInstanceOf(ReportTable::class)->and($table->columns)->not->toBeEmpty();
    }

    $sources = booking(fn (): ReportTable => $runner->run('sources', $filter));
    $stay = booking(fn (): ReportTable => $runner->run('length-of-stay', $filter));

    expect($sources->rows[0][0])->toBe('Online')->and($sources->rows[0][3])->toBe('4')->and($sources->totals[1])->toBe('2')->and($stay->totals[1])->toBe('1')->and($stay->rows[1][1])->toBe('1');
});

it('names the guests and countries in the guest reports', function (): void {
    rpCountry();
    rpBook(['status' => 'checked_out']);
    rpBook(['status' => 'checked_out', 'check_in' => '2026-03-20', 'check_out' => '2026-03-22']);
    booking(fn () => Guest::query()->where('first_name', 'Rahim')->update(['nationality_code' => 'GB']));
    $runner = app(ReportRunner::class);
    $filter = new ReportFilter([bookingIds()['property']], '2026-03-01', '2026-03-31', 'arrival', 10, 'BDT');

    $nationalities = booking(fn (): ReportTable => $runner->run('nationalities', $filter));
    $repeat = booking(fn (): ReportTable => $runner->run('repeat-guests', $filter));
    $top = booking(fn (): ReportTable => $runner->run('top-guests', $filter));

    expect($nationalities->rows[0][0])->toBe('United Kingdom')->and($nationalities->rows[0][1])->toBe('1')->and($nationalities->rows[0][2])->toBe('2')
        ->and($repeat->rows[0][0])->toContain('Rahim Uddin')->and($repeat->rows[0][2])->toBe('2')->and($top->rows[0][0])->toContain('Rahim Uddin')->and($top->rows[0][3])->toBe('20,000.00');
});
