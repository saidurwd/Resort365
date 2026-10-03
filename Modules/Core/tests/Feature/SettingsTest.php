<?php

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantCache;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Contracts\Settings;
use Modules\Core\Database\Seeders\ReferenceDataSeeder;
use Modules\Core\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed(ReferenceDataSeeder::class);
    withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));
    withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley']));
});

function inTenant(string $slug, Closure $callback): mixed
{
    return app(TenantContext::class)->run(tenant($slug), $callback);
}

it('saves settings and reads them back per property', function (): void {
    $settings = app(Settings::class);

    inTenant('sunrise', function () use ($settings): void {
        $settings->set('core.night_audit_time', '13:00');
        $settings->set('core.night_audit_time', '15:30', propertyId: 1);
        $settings->set('core.night_audit_time', '12:00', propertyId: 2);

        expect($settings->get('core.night_audit_time'))->toBe('13:00')
            ->and($settings->get('core.night_audit_time', 1))->toBe('15:30')
            ->and($settings->get('core.night_audit_time', 2))->toBe('12:00')
            ->and($settings->get('core.night_audit_time', 3))->toBe('13:00')
            ->and($settings->stored('core.night_audit_time', 3))->toBeNull();
    });
});

it('falls back to the default and keeps tenants apart', function (): void {
    $settings = app(Settings::class);

    inTenant('sunrise', fn () => $settings->set('core.default_deposit_percent', 40));

    expect(inTenant('sunrise', fn () => $settings->get('core.default_deposit_percent')))->toBe(40)
        ->and(inTenant('greenvalley', fn () => $settings->get('core.default_deposit_percent')))->toBe(30);
});

it('casts values to their type', function (): void {
    $settings = app(Settings::class);

    inTenant('sunrise', function () use ($settings): void {
        $settings->set('core.default_deposit_percent', '45');
        $settings->set('iam.require_two_factor', '1');

        expect($settings->get('core.default_deposit_percent'))->toBe(45)
            ->and($settings->get('iam.require_two_factor'))->toBeTrue()
            ->and($settings->get('core.currency'))->toBe('BDT');
    });
});

it('validates values and scope', function (mixed $key, mixed $value, ?int $property): void {
    inTenant('sunrise', fn () => app(Settings::class)->set($key, $value, $property));
})->with([
    'bad time' => ['core.night_audit_time', '25:99', null],
    'unknown currency' => ['core.currency', 'XYZ', null],
    'unknown timezone' => ['core.timezone', 'Mars/Olympus', null],
    'deposit above 100' => ['core.default_deposit_percent', 150, null],
    'not a choice' => ['core.date_format', 'Y', null],
])->throws(ValidationException::class);

it('refuses a property value for a company-wide setting', function (): void {
    inTenant('sunrise', fn () => app(Settings::class)->set('core.currency', 'USD', 1));
})->throws(InvalidArgumentException::class);

it('removes a value with null, falling back again', function (): void {
    $settings = app(Settings::class);

    inTenant('sunrise', function () use ($settings): void {
        $settings->set('core.night_audit_time', '11:00', 1);
        $settings->set('core.night_audit_time', null, 1);

        expect($settings->get('core.night_audit_time', 1))->toBe('02:00');
    });
});

it('caches values per tenant and refreshes on save', function (): void {
    $settings = app(Settings::class);

    inTenant('sunrise', function () use ($settings): void {
        $settings->get('core.timezone');
        expect(app(TenantCache::class)->has('core.settings'))->toBeTrue();

        $settings->set('core.timezone', 'Asia/Kolkata');
        expect(app(TenantCache::class)->has('core.settings'))->toBeFalse()
            ->and($settings->get('core.timezone'))->toBe('Asia/Kolkata');
    });
});

it('saves a settings group from the screen and audits the change', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));

    get(tenantUrl('sunrise', '/core/settings?group=General'))->assertOk()->assertSee('Asia/Dhaka');

    put(tenantUrl('sunrise', '/core/settings/General'), [
        'core__timezone' => 'Asia/Dhaka',
        'core__currency' => 'USD',
        'core__date_format' => 'Y-m-d',
    ])->assertSessionHasNoErrors()->assertRedirect();

    inTenant('sunrise', function (): void {
        expect(app(Settings::class)->get('core.currency'))->toBe('USD')
            ->and(Activity::query()->where('log_name', 'setting')->count())->toBeGreaterThan(0);
    });

    put(tenantUrl('sunrise', '/core/settings/Operations'), ['core__night_audit_time' => 'noon'])->assertSessionHasErrors('core__night_audit_time');
});

it('protects the settings screen with permissions', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));
    get(tenantUrl('sunrise', '/core/settings'))->assertForbidden();

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::Auditor));
    get(tenantUrl('sunrise', '/core/settings'))->assertOk()->assertDontSee(__('Save settings'));
    put(tenantUrl('sunrise', '/core/settings/General'), ['core__currency' => 'USD'])->assertForbidden();
});
