<?php

/*
| Step 0.8 "Done when": a user assigned only to Sunrise Sylhet can never see Sunrise Cox's Bazar
| data, and the switcher changes the context everywhere.
*/
use App\Http\Middleware\SetCurrentProperty;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Core\Contracts\Settings;
use Modules\Core\Models\Activity;
use Modules\IAM\Models\User;
use Modules\Property\Models\Property;
use Tests\Fixtures\Tenancy\PropertyProbe;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $tenant = withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));

    app(TenantContext::class)->run($tenant, function (): void {
        Property::factory()->create(['code' => 'CXB', 'name' => "Sunrise Cox's Bazar", 'business_date' => '2026-10-01']);
        Property::factory()->create(['code' => 'SYL', 'name' => 'Sunrise Sylhet', 'business_date' => '2026-10-02']);
    });
});

function prop(string $code): Property
{
    // The last HTTP request's property restriction lingers in tests; look up unrestricted.
    app(PropertyContext::class)->clear();

    return app(TenantContext::class)->run(tenant('sunrise'), fn (): Property => Property::query()->where('code', $code)->firstOrFail());
}

function assignTo(User $user, string ...$codes): User
{
    foreach ($codes as $code) {
        DB::table('property_user')->insert(['tenant_id' => $user->tenant_id, 'property_id' => prop($code)->id, 'user_id' => $user->id]);
    }

    return $user;
}

it('never shows a Sylhet-only user anything of Cox\'s Bazar', function (): void {
    $user = assignTo(tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager), 'SYL');
    app(TenantContext::class)->run(tenant('sunrise'), function (): void {
        PropertyProbe::factory()->create(['property_id' => prop('CXB')->id, 'name' => 'Cox data']);
        PropertyProbe::factory()->create(['property_id' => prop('SYL')->id, 'name' => 'Sylhet data']);
    });
    Route::domain('{tenant}.'.config('tenancy.central_domain'))->middleware(['web', 'tenant', 'auth'])
        ->get('/_probes', fn () => PropertyProbe::query()->pluck('name')->implode(','));
    actingAs($user);

    get(tenantUrl('sunrise', '/dashboard'))->assertOk()->assertSee('Sunrise Sylhet')->assertDontSee("Sunrise Cox's Bazar");
    get(tenantUrl('sunrise', '/property/properties'))->assertOk()->assertSee('Sunrise Sylhet')->assertDontSee("Cox's Bazar");
    get(tenantUrl('sunrise', '/property/properties/'.prop('CXB')->id.'/edit'))->assertNotFound();
    post(tenantUrl('sunrise', '/property/switch/'.prop('CXB')->id))->assertNotFound();
    get(tenantUrl('sunrise', '/core/settings?property='.prop('CXB')->id))->assertNotFound();
    get(tenantUrl('sunrise', '/core/document-sequences?property='.prop('CXB')->id))->assertNotFound();
    get(tenantUrl('sunrise', '/_probes'))->assertOk()->assertSeeText('Sylhet data')->assertDontSeeText('Cox data');
});

it('gives Tenant Owner and Auditor every property without assignments', function (DefaultRole $role): void {
    actingAs(tenantUserAs(tenant('sunrise'), $role));

    get(tenantUrl('sunrise', '/dashboard'))->assertOk()->assertSeeHtml('data-property-option="'.prop('CXB')->id.'"')->assertSeeHtml('data-property-option="'.prop('SYL')->id.'"');
})->with([DefaultRole::TenantOwner, DefaultRole::Auditor]);

it('warns a user without any property access', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));

    get(tenantUrl('sunrise', '/dashboard'))->assertOk()->assertSee(__('No property access'));
});

it('switches the current property everywhere', function (): void {
    $user = assignTo(tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager), 'CXB', 'SYL');
    Route::domain('{tenant}.'.config('tenancy.central_domain'))->middleware(['web', 'tenant', 'auth'])
        ->get('/_current', fn (PropertyContext $context) => (string) $context->currentId());
    actingAs($user);

    get(tenantUrl('sunrise', '/dashboard'))->assertSeeHtml('data-current-property')->assertSee("Sunrise Cox's Bazar");
    get(tenantUrl('sunrise', '/_current'))->assertSeeText((string) prop('CXB')->id);

    post(tenantUrl('sunrise', '/property/switch/'.prop('SYL')->id))->assertRedirect()->assertSessionHas(SetCurrentProperty::SESSION_KEY, prop('SYL')->id);

    get(tenantUrl('sunrise', '/_current'))->assertSeeText((string) prop('SYL')->id);
    get(tenantUrl('sunrise', '/dashboard'))->assertSeeHtml('<span data-current-property>Sunrise Sylhet</span>')->assertSeeHtml('data-business-date')->assertSee('02 Oct 2026');

    app(TenantContext::class)->run(tenant('sunrise'), fn () => app(Settings::class)->set('core.date_format', 'Y-m-d'));
    get(tenantUrl('sunrise', '/dashboard'))->assertSee('2026-10-02');
});

it('saves and reads settings per property from the settings screen', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));
    $syl = prop('SYL')->id;

    get(tenantUrl('sunrise', '/core/settings?group=Operations&property='.$syl))->assertOk()->assertSeeHtml('data-property-scope')->assertSee(__('Company-wide: :value', ['value' => '02:00']));
    put(tenantUrl('sunrise', '/core/settings/Operations?property='.$syl), ['core__night_audit_time' => '03:30', 'core__default_deposit_percent' => '50'])
        ->assertSessionHasNoErrors();
    get(tenantUrl('sunrise', '/core/settings?group=General&property='.$syl))->assertNotFound();

    app(TenantContext::class)->run(tenant('sunrise'), function () use ($syl): void {
        $settings = app(Settings::class);
        expect($settings->get('core.night_audit_time', $syl))->toBe('03:30')
            ->and($settings->get('core.default_deposit_percent', $syl))->toBe(50)
            ->and($settings->get('core.night_audit_time', prop('CXB')->id))->toBe('02:00')
            ->and($settings->get('core.night_audit_time'))->toBe('02:00');
    });
});

it('edits numbering per property', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));
    $syl = prop('SYL')->id;

    put(tenantUrl('sunrise', '/core/document-sequences/reservation?property='.$syl), ['prefix' => 'SYL', 'format' => '{PREFIX}-{SEQ:4}', 'next_number' => 7, 'reset' => 'never'])
        ->assertSessionHasNoErrors();
    get(tenantUrl('sunrise', '/core/document-sequences?property='.$syl))->assertSee('SYL-0007');
    get(tenantUrl('sunrise', '/core/document-sequences'))->assertDontSee('SYL-0007');
});

it('assigns users to properties on the access grid, with an audit entry', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));
    $clerk = tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent, ['name' => 'Sabbir Hossain']);

    get(tenantUrl('sunrise', '/property/access'))->assertOk()->assertSee('Sabbir Hossain')->assertSeeHtml('data-access-grid');
    put(tenantUrl('sunrise', '/property/access'), ['users' => [$clerk->id], 'access' => [prop('SYL')->id => [$clerk->id]]])->assertSessionHasNoErrors();

    expect(DB::table('property_user')->where('user_id', $clerk->id)->pluck('property_id')->all())->toBe([prop('SYL')->id])
        ->and(app(TenantContext::class)->run(tenant('sunrise'), fn (): bool => Activity::query()->where('description', 'Property access changed')->exists()))->toBeTrue();

    put(tenantUrl('sunrise', '/property/access'), ['users' => [$clerk->id], 'access' => [999999 => [$clerk->id]]])->assertSessionHasErrors('access');

    actingAs($clerk);
    get(tenantUrl('sunrise', '/property/access'))->assertForbidden();
});
