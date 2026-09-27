<?php

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ExchangeRates;
use Modules\Core\Database\Seeders\ReferenceDataSeeder;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;
use Modules\Core\Models\ExchangeRate;
use Modules\Core\Models\Timezone;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(fn () => seed(ReferenceDataSeeder::class));

it('seeds ISO countries, currencies and timezones idempotently', function (): void {
    $counts = [DB::table('countries')->count(), DB::table('currencies')->count(), DB::table('timezones')->count()];
    seed(ReferenceDataSeeder::class);

    expect([DB::table('countries')->count(), DB::table('currencies')->count(), DB::table('timezones')->count()])->toBe($counts)
        ->and($counts[0])->toBeGreaterThan(240)
        ->and(Country::query()->find('BD')?->getAttribute('iso3'))->toBe('BGD')
        ->and(Currency::query()->find('BDT')?->getAttribute('decimals'))->toBe(2)
        ->and(Currency::query()->find('JPY')?->getAttribute('decimals'))->toBe(0)
        ->and(Timezone::query()->find('Asia/Dhaka')?->getAttribute('country_code'))->toBe('BD')
        ->and(Timezone::query()->find('Asia/Dhaka')?->getAttribute('utc_offset'))->toBe('+06:00');
});

it('looks up exchange rates by date, including inverse pairs', function (): void {
    $tenant = Tenant::factory()->create();

    app(TenantContext::class)->run($tenant, function (): void {
        ExchangeRate::query()->create(['base_currency' => 'USD', 'quote_currency' => 'BDT', 'rate' => '110.00000000', 'effective_date' => '2025-01-01']);
        ExchangeRate::query()->create(['base_currency' => 'USD', 'quote_currency' => 'BDT', 'rate' => '122.00000000', 'effective_date' => '2026-01-01']);

        $rates = app(ExchangeRates::class);

        expect($rates->rate('USD', 'BDT', CarbonImmutable::parse('2025-06-30')))->toBe('110.00000000')
            ->and($rates->rate('usd', 'bdt', CarbonImmutable::parse('2026-03-01')))->toBe('122.00000000')
            ->and($rates->rate('BDT', 'USD', CarbonImmutable::parse('2026-03-01')))->toBe('0.00819672')
            ->and($rates->rate('USD', 'BDT', CarbonImmutable::parse('2024-12-31')))->toBeNull()
            ->and($rates->rate('EUR', 'BDT'))->toBeNull()
            ->and($rates->rate('BDT', 'BDT'))->toBe('1.00000000');
    });
});

it('keeps exchange rates per tenant', function (): void {
    [$a, $b] = Tenant::factory()->count(2)->create()->all();
    app(TenantContext::class)->run($a, fn () => ExchangeRate::query()->create(['base_currency' => 'USD', 'quote_currency' => 'BDT', 'rate' => '120', 'effective_date' => '2026-01-01']));

    expect(app(TenantContext::class)->run($b, fn (): ?string => app(ExchangeRates::class)->rate('USD', 'BDT')))->toBeNull();
});
