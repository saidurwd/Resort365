<?php

use App\Models\Tenant;
use App\Support\Tenancy\PropertyAccess;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\IAM\Models\User;
use Modules\Property\Enums\BookingMode;
use Modules\Property\Models\Amenity;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\Department;
use Modules\Property\Models\Property;
use Modules\Property\Services\OccupancyCalculator;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

it('produces the full demo: tenants, properties, one user per role and property access', function (): void {
    seed(DatabaseSeeder::class);

    expect(Tenant::query()->orderBy('slug')->pluck('slug')->all())->toBe(['greenvalley', 'sunrise']);

    app(TenantContext::class)->run(tenant('sunrise'), function (): void {
        expect(Property::query()->orderBy('code')->pluck('name', 'code')->all())->toBe(['CXB' => "Sunrise Cox's Bazar", 'SYL' => 'Sunrise Sylhet'])
            ->and(User::query()->count())->toBe(19);

        $access = fn (string $email): array => array_values(app(PropertyAccess::class)->accessibleProperties(User::query()->where('email', $email)->firstOrFail()));

        expect($access('frontdesk.sylhet@sunrise.test'))->toBe(['Sunrise Sylhet'])
            ->and($access('frontdesk@sunrise.test'))->toBe(["Sunrise Cox's Bazar"])
            ->and($access('owner@sunrise.test'))->toBe(["Sunrise Cox's Bazar", 'Sunrise Sylhet'])
            ->and($access('gm@sunrise.test'))->toBe(["Sunrise Cox's Bazar", 'Sunrise Sylhet']);
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

    app(TenantContext::class)->run(tenant('sunrise'), function () use ($layout): void {
        expect($layout('CXB'))->toBe([1, 1, 1, 2, 2, 2, 3, 3])
            ->and($layout('SYL'))->toBe([2, 2, 3, 3])
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
