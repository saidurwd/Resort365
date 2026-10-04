<?php

/*
| BusinessDates (Step 2.6): only the night audit moves a property's business date, one day at a
| time, and only from the date it expects.
*/
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Property\Contracts\BusinessDates;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\PropertySummary;
use Modules\Property\Exceptions\BusinessDateMismatch;
use Modules\Property\Models\Property;

uses(RefreshDatabase::class);

it('moves the business date on by one day from the expected date only', function (): void {
    $tenant = Tenant::factory()->create();

    app(TenantContext::class)->run($tenant, function (): void {
        $property = Property::factory()->create(['business_date' => '2026-11-10']);
        $dates = app(BusinessDates::class);

        expect($dates->advance($property->id, '2026-11-10'))->toBe('2026-11-11')
            ->and(app(PropertyDirectory::class)->find($property->id)?->businessDate)->toBe('2026-11-11')
            ->and(fn () => $dates->advance($property->id, '2026-11-10'))->toThrow(BusinessDateMismatch::class)
            ->and($property->fresh()?->business_date->toDateString())->toBe('2026-11-11');
    });
});

it('lists the tenant\'s active properties by name', function (): void {
    $tenant = Tenant::factory()->create();

    app(TenantContext::class)->run($tenant, function (): void {
        Property::factory()->create(['name' => 'Sylhet']);
        Property::factory()->create(['name' => 'Cox\'s Bazar']);
        Property::factory()->create(['name' => 'Closed', 'status' => 'inactive']);

        expect(array_map(fn (PropertySummary $property): string => $property->name, app(PropertyDirectory::class)->all()))->toBe(['Cox\'s Bazar', 'Sylhet']);
    });
});
