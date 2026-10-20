<?php

/*
| "Done when" (Step 7.1): the dashboard loads in under 2 seconds with a year of demo data. A year of night audit days and a year
| of bookings are seeded the way the demo does, and the page is loaded with its queries counted.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Database\Seeders\DemoReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\FrontOffice\Models\DailyStatistic;
use Modules\Reservation\Models\Reservation;

use function Pest\Laravel\get;

require_once __DIR__.'/../Support/reports-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    // The demo gives guests these nationalities, which the reference data has in a real installation.
    foreach (['GB', 'US', 'IN', 'DE', 'CN', 'AU', 'FR', 'JP', 'SG', 'AE'] as $index => $code) {
        rpCountry($code, $code, 'X'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), (string) (900 + $index));
    }

    DemoReports::seed(tenant('sunrise'), bookingIds()['property']);
});

it('has a year of statistics and bookings to work with', function (): void {
    expect(booking(fn (): int => DailyStatistic::query()->count()))->toBe(365)->and(booking(fn (): int => Reservation::query()->count()))->toBeGreaterThan(100);
});

it('loads the dashboard in under two seconds with a few queries, whatever the amount of history', function (): void {
    staffUser(DefaultRole::GeneralManager);
    get(tenantUrl('sunrise', '/dashboard'))->assertOk();
    app('cache')->flush();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $started = microtime(true);
    $page = get(tenantUrl('sunrise', '/dashboard'));
    $seconds = microtime(true) - $started;

    $page->assertOk()->assertSeeHtml('data-chart="months"');
    expect($seconds)->toBeLessThan(2.0)->and($queries)->toBeLessThan(80);
});

it('runs the rooms and guest reports over a year in a couple of seconds', function (): void {
    staffUser(DefaultRole::GeneralManager);
    $year = date('Y-m-d', strtotime('-365 days')).'&to='.date('Y-m-d');

    foreach (['sources', 'pace', 'cancellations', 'length-of-stay', 'nationalities', 'repeat-guests', 'top-guests', 'top-companies'] as $key) {
        $started = microtime(true);
        get(tenantUrl('sunrise', "/reports/{$key}?from={$year}&by=arrival"))->assertOk();

        expect(microtime(true) - $started)->toBeLessThan(3.0);
    }
});
