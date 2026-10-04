<?php

use App\Models\Tenant;
use App\Support\Tenancy\PropertyAccess;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Models\Payment;
use Modules\Core\Contracts\TaxEngine;
use Modules\Core\Models\TaxCategory;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Guest\Models\Company;
use Modules\Guest\Models\Guest;
use Modules\Guest\Models\TravelAgent;
use Modules\IAM\Models\User;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\UnitTypeSummary;
use Modules\Property\Enums\BookingMode;
use Modules\Property\Models\Amenity;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\Department;
use Modules\Property\Models\Property;
use Modules\Property\Services\OccupancyCalculator;
use Modules\Rates\Models\CancellationPolicy;
use Modules\Rates\Models\DepositPolicy;
use Modules\Rates\Models\Promotion;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Services\RateCalendar;
use Modules\Reservation\Jobs\ExpireTentativeHolds;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Quote;
use Modules\Reservation\Models\Reservation;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

it('produces the full demo: tenants, properties, one user per role and property access', function (): void {
    seed(DatabaseSeeder::class);

    expect(Tenant::query()->orderBy('slug')->pluck('slug')->all())->toBe(['greenvalley', 'rodela'])
        ->and(tenant('rodela')->name)->toBe('Rodela Eco Resort');

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        expect(Property::query()->pluck('name', 'code')->all())->toBe(['CXB' => 'Rodela Eco Resort'])
            ->and(User::query()->count())->toBe(18);

        $access = fn (string $email): array => array_values(app(PropertyAccess::class)->accessibleProperties(User::query()->where('email', $email)->firstOrFail()));

        expect($access('frontdesk@rodelaresort.com'))->toBe(['Rodela Eco Resort'])
            ->and($access('owner@rodelaresort.com'))->toBe(['Rodela Eco Resort']);
    });

    app(TenantContext::class)->run(tenant('greenvalley'), function (): void {
        expect(Property::query()->pluck('name')->all())->toBe(['Green Valley'])
            ->and(User::query()->count())->toBe(18);
    });
});

it('sets up each resort with single-room and multi-room cottages (Step 1.1)', function (): void {
    seed(DatabaseSeeder::class);

    $layout = fn (string $code): array => Cottage::query()->with(['rooms.roomType'])
        ->whereHas('property', fn ($query) => $query->where('code', $code))->orderBy('sort_order')->get()
        ->map(fn (Cottage $cottage): int => $cottage->rooms->count())->all();

    app(TenantContext::class)->run(tenant('rodela'), function () use ($layout): void {
        expect($layout('CXB'))->toBe([1, 1, 1, 2, 2, 2, 3, 3])
            ->and(Amenity::query()->count())->toBe(15)
            ->and(Department::query()->count())->toBe(11);

        $villa = Cottage::query()->with('rooms.roomType')->where('code', 'C07')->sole();
        expect($villa->booking_mode)->toBe(BookingMode::WholeOnly)
            ->and($villa->rooms->pluck('roomType.code')->all())->toBe(['FS', 'DK', 'TW'])
            ->and(app(OccupancyCalculator::class)->forCottage($villa))->toBe(11);
    });

    app(TenantContext::class)->run(tenant('greenvalley'), function () use ($layout): void {
        expect($layout('GVR'))->toBe([1, 1, 1, 2, 4]);
    });
});

it('seeds guests to search at scale, with a duplicate pair and a blacklisted guest (Step 1.2)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        expect(Guest::query()->count())->toBe(10_000)
            ->and(Company::query()->count())->toBe(8)
            ->and(TravelAgent::query()->count())->toBe(5)
            ->and(Guest::query()->where('phone', '+8801711000001')->count())->toBe(2)
            ->and(Guest::query()->where('is_blacklisted', true)->pluck('first_name')->all())->toBe(['Kamal'])
            ->and(array_map(fn (GuestSummary $guest) => $guest->name, app(GuestLookup::class)->search('01711-000001')))->toEqualCanonicalizing(['Rahim Uddin', 'Mr Rahim Uddin']);

        // Bulk-inserted guests are readable like any other: decrypted ID, hash in step.
        $guest = Guest::query()->where('phone', '+8801710000001')->sole();
        expect($guest->id_number)->toBe('1000000001')
            ->and(app(GuestLookup::class)->search('1000000001')[0]->id)->toBe($guest->id);
    });

    app(TenantContext::class)->run(tenant('greenvalley'), fn () => expect(Guest::query()->count())->toBe(200));
});

it('seeds taxes, seasons, rate plans and rates (Step 1.3)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        $room = TaxCategory::query()->where('code', 'ROOM')->sole();
        $cxb = Property::query()->where('code', 'CXB')->sole();
        $plan = RatePlan::query()->where('property_id', $cxb->id)->where('code', 'RO')->sole();
        $units = app(InventoryCatalog::class)->unitTypes($cxb->id);
        $year = CarbonImmutable::now()->year;
        $days = fn (string $from, string $to): array => app(RateCalendar::class)->rates($plan, array_map(fn (UnitTypeSummary $unit) => $unit->key(), $units), CarbonImmutable::parse($from), CarbonImmutable::parse($to));

        expect(app(TaxEngine::class)->calculate('1000', $room->id)->gross)->toBe('1265.00')
            ->and(RatePlan::query()->where('property_id', $cxb->id)->pluck('code')->sort()->values()->all())->toBe(['BB', 'HB', 'NRF', 'RO'])
            ->and(DepositPolicy::query()->where('property_id', $cxb->id)->where('is_default', true)->value('name'))->toBe('Standard advance')
            ->and(CancellationPolicy::query()->where('property_id', $cxb->id)->pluck('name')->sort()->values()->all())->toBe(['Flexible', 'Non-refundable'])
            ->and(RatePlan::query()->where('code', 'NRF')->sole()->cancellationPolicy?->name)->toBe('Non-refundable')
            ->and(Promotion::query()->where('property_id', $cxb->id)->pluck('code')->filter()->sort()->values()->all())->toBe(['EARLYBIRD', 'MONSOON20']);

        $villaKey = collect($units)->firstWhere('code', 'FV')?->key();
        $kingKey = collect($units)->firstWhere('code', 'DK')?->key();
        $newYear = $days("{$year}-12-30", "{$year}-12-31");

        // Deluxe King: 6,000 base; Peak ×1.4 = 8,400; New Year's Eve date price 6,000 × 1.8 = 10,800.
        expect($newYear[$kingKey]["{$year}-12-31"]?->amount)->toBe('10800.00')
            ->and($newYear[$kingKey]["{$year}-12-30"]?->seasonName)->toBe('Peak')
            ->and($newYear[$villaKey]["{$year}-12-31"]?->amount)->toBe('45000.00');
    });

    app(TenantContext::class)->run(tenant('greenvalley'), fn () => expect(RatePlan::query()->pluck('code')->sort()->values()->all())->toBe(['BB', 'RO']));
});

it('seeds bookings that lock their rooms: tentative, confirmed by a deposit, and an overdue hold (Steps 1.6–1.8)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        $bookings = Reservation::query()->with('items')->orderBy('check_in')->get();

        // Room 601 (overdue hold, 1 night), room 501 (2 nights), room 101 (3 nights), Sunset Villa (3 rooms × 3 nights).
        expect($bookings->pluck('status')->map->value->all())->toBe(['tentative', 'tentative', 'confirmed', 'tentative'])
            ->and($bookings[0]->deposit_due_at?->isPast())->toBeTrue()
            ->and($bookings[1]->deposit_percent)->toBe('50.00')
            ->and($bookings[2]->amount_paid)->toBe($bookings[2]->deposit_required)
            ->and(Payment::query()->pluck('receipt_no')->all())->toHaveCount(1)
            ->and(InventoryLock::query()->where('lock_type', 'reservation')->count())->toBe(1 + 2 + 3 + 3 * 3)
            ->and(Quote::query()->orderBy('check_in')->get()->map(fn (Quote $quote): string => $quote->currentStatus()->value)->all())->toBe(['sent', 'expired']);
    });

    ExpireTentativeHolds::dispatchSync();
    app(TenantContext::class)->run(tenant('rodela'), fn () => expect(Reservation::query()->orderBy('check_in')->pluck('status')->map->value->all())
        ->toBe(['cancelled', 'tentative', 'confirmed', 'tentative']));
});
