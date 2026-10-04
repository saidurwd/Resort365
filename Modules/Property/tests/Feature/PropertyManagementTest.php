<?php

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Database\Seeders\ReferenceDataSeeder;
use Modules\Core\Models\Activity;
use Modules\Core\Models\DocumentSequence;
use Modules\Property\Models\Property;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('attachments');
    seed(ReferenceDataSeeder::class);
    withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function propertyInput(array $overrides = []): array
{
    return [
        'code' => 'cxb', 'name' => "Sunrise Cox's Bazar", 'legal_name' => 'Sunrise Resorts Ltd', 'email' => 'cxb@sunrise.test',
        'phone' => '+8801700000000', 'address_line1' => 'Marine Drive', 'city' => "Cox's Bazar",
        'country_code' => 'BD', 'timezone' => 'Asia/Dhaka', 'currency_code' => 'BDT',
        'check_in_time' => '14:00', 'check_out_time' => '11:30', 'tax_registration_no' => 'BIN-123', 'status' => 'active',
        ...$overrides,
    ];
}

function propertyNamed(string $code): Property
{
    // The last HTTP request's property restriction lingers in tests; look up unrestricted.
    app(PropertyContext::class)->clear();

    return app(TenantContext::class)->run(tenant('sunrise'), fn (): Property => Property::query()->where('code', $code)->firstOrFail());
}

it('creates a property with its business date, sequences and an audit entry', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));

    get(tenantUrl('sunrise', '/property/properties/create'))->assertOk();
    post(tenantUrl('sunrise', '/property/properties'), propertyInput(['logo' => UploadedFile::fake()->image('logo.png', 200, 80)]))
        ->assertSessionHasNoErrors()->assertRedirect(tenantUrl('sunrise', '/property/properties'));

    $property = propertyNamed('CXB');
    expect($property->check_out_time)->toStartWith('11:30')
        ->and($property->business_date->toDateString())->toBe(now('Asia/Dhaka')->toDateString())
        ->and($property->getFirstMedia(Property::LOGO))->not->toBeNull();

    app(TenantContext::class)->run(tenant('sunrise'), function () use ($property): void {
        expect(DocumentSequence::query()->where('property_id', $property->id)->count())->toBe(8)
            ->and(Activity::query()->where('subject_type', 'property')->where('event', 'created')->exists())->toBeTrue();
    });

    get(tenantUrl('sunrise', '/property/properties'))->assertOk()->assertSee("Sunrise Cox's Bazar");
});

it('validates properties', function (array $input, string $field): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));
    app(TenantContext::class)->run(tenant('sunrise'), fn () => Property::factory()->create(['code' => 'SYL']));

    post(tenantUrl('sunrise', '/property/properties'), propertyInput($input))->assertSessionHasErrors($field);
})->with([
    'duplicate code' => [['code' => 'syl'], 'code'],
    'unknown country' => [['country_code' => 'XX'], 'country_code'],
    'unknown timezone' => [['timezone' => 'Mars/Base'], 'timezone'],
    'unknown currency' => [['currency_code' => 'XYZ'], 'currency_code'],
    'bad time' => [['check_in_time' => '2pm'], 'check_in_time'],
]);

it('allows the same code in another tenant', function (): void {
    $other = Tenant::factory()->create(['slug' => 'greenvalley']);
    app(TenantContext::class)->run($other, fn () => Property::factory()->create(['code' => 'CXB']));
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));

    post(tenantUrl('sunrise', '/property/properties'), propertyInput())->assertSessionHasNoErrors();
});

it('edits and deactivates a property', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));
    post(tenantUrl('sunrise', '/property/properties'), propertyInput());
    $property = propertyNamed('CXB');

    get(tenantUrl('sunrise', '/property/properties/'.$property->id.'/edit'))->assertOk()->assertSee('Marine Drive');
    put(tenantUrl('sunrise', '/property/properties/'.$property->id), propertyInput(['name' => 'Sunrise CXB', 'status' => 'inactive']))->assertSessionHasNoErrors();

    expect(propertyNamed('CXB')->name)->toBe('Sunrise CXB')
        ->and(propertyNamed('CXB')->isActive())->toBeFalse();
});

it('protects property screens with permissions', function (): void {
    $property = app(TenantContext::class)->run(tenant('sunrise'), fn (): Property => Property::factory()->create());

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));
    get(tenantUrl('sunrise', '/property/properties'))->assertForbidden();

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager));
    get(tenantUrl('sunrise', '/property/properties/create'))->assertForbidden();

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::Auditor));
    get(tenantUrl('sunrise', '/property/properties'))->assertOk();
    get(tenantUrl('sunrise', '/property/properties/'.$property->id.'/edit'))->assertForbidden();
});
