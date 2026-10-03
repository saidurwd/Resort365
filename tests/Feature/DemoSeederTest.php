<?php

use App\Models\Tenant;
use App\Support\Tenancy\PropertyAccess;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\IAM\Models\User;
use Modules\Property\Models\Property;

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
