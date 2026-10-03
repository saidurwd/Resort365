<?php

/*
| Amenities and departments: one catalogue per tenant, shared by its properties.
*/

use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Activity;
use Modules\Property\Enums\AmenityCategory;
use Modules\Property\Models\Amenity;
use Modules\Property\Models\CottageType;
use Modules\Property\Models\Department;
use Modules\Property\Tests\Support\PropertySetup;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;
use function Pest\Laravel\withSession;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    PropertySetup::tenant();
});

it('manages the amenities catalogue', function (): void {
    actingAs(PropertySetup::user(DefaultRole::GeneralManager, 'CXB'));

    post(tenantUrl('sunrise', '/property/amenities'), ['name' => 'Wi-Fi', 'icon' => 'bi-wifi', 'category' => 'in_room', 'is_active' => '1'])
        ->assertSessionHasNoErrors()->assertRedirect(tenantUrl('sunrise', '/property/amenities'));
    post(tenantUrl('sunrise', '/property/amenities'), ['name' => 'Wi-Fi', 'icon' => 'wifi', 'category' => 'garage'])->assertSessionHasErrors(['name', 'icon', 'category']);

    $wifi = PropertySetup::run(fn (): Amenity => Amenity::query()->sole());
    expect($wifi->category)->toBe(AmenityCategory::InRoom);

    get(tenantUrl('sunrise', '/property/amenities'))->assertOk()->assertSee('Wi-Fi');
    get(tenantUrl('sunrise', '/property/amenities/'.$wifi->id.'/edit'))->assertOk();
    put(tenantUrl('sunrise', '/property/amenities/'.$wifi->id), ['name' => 'Free Wi-Fi', 'icon' => 'bi-wifi', 'category' => 'service', 'is_active' => '0'])->assertSessionHasNoErrors();

    $wifi = PropertySetup::run(fn (): Amenity => $wifi->fresh() ?? throw new RuntimeException('Amenity missing'));
    expect($wifi->name)->toBe('Free Wi-Fi')->and($wifi->is_active)->toBeFalse();
});

it('removes a deleted amenity from every type', function (): void {
    $propertyId = PropertySetup::property('CXB')->id;
    [$wifi, $type] = PropertySetup::run(function () use ($propertyId): array {
        $wifi = Amenity::factory()->create(['name' => 'Wi-Fi']);
        $type = CottageType::factory()->create(['property_id' => $propertyId]);
        $type->amenities()->attach($wifi->id, ['tenant_id' => $type->tenant_id]);

        return [$wifi, $type];
    });

    actingAs(PropertySetup::user(DefaultRole::GeneralManager, 'CXB'));
    delete(tenantUrl('sunrise', '/property/amenities/'.$wifi->id))->assertRedirect(tenantUrl('sunrise', '/property/amenities'));

    expect(DB::table('amenity_links')->count())->toBe(0)
        ->and(PropertySetup::run(fn (): int => $type->amenities()->count()))->toBe(0);
});

it('records amenity changes on a type in its audit trail', function (): void {
    $wifi = PropertySetup::run(fn (): Amenity => Amenity::factory()->create(['name' => 'Wi-Fi']));

    actingAs(PropertySetup::user(DefaultRole::GeneralManager, 'CXB'));
    withSession(PropertySetup::current('CXB'));
    post(tenantUrl('sunrise', '/property/cottage-types'), ['code' => 'hc', 'name' => 'Honeymoon Cottage', 'bedrooms' => 1, 'max_occupancy' => 2, 'amenity_ids' => [$wifi->id]])
        ->assertSessionHasNoErrors();

    $entry = PropertySetup::run(fn (): ?Activity => Activity::query()->where('subject_type', 'cottage_type')->where('properties->attributes->amenities', 'Wi-Fi')->first());
    expect($entry)->not->toBeNull();
});

it('manages departments', function (): void {
    actingAs(PropertySetup::user(DefaultRole::HrManager));

    post(tenantUrl('sunrise', '/property/departments'), ['code' => 'fo', 'name' => 'Front Office', 'description' => 'Reception'])
        ->assertSessionHasNoErrors()->assertRedirect(tenantUrl('sunrise', '/property/departments'));
    post(tenantUrl('sunrise', '/property/departments'), ['code' => 'FO', 'name' => ''])->assertSessionHasErrors(['code', 'name']);

    $department = PropertySetup::run(fn (): Department => Department::query()->sole());
    expect($department->code)->toBe('FO')->and($department->is_active)->toBeTrue();

    get(tenantUrl('sunrise', '/property/departments'))->assertOk()->assertSee('Front Office');
    get(tenantUrl('sunrise', '/property/departments/'.$department->id.'/edit'))->assertOk();
    put(tenantUrl('sunrise', '/property/departments/'.$department->id), ['code' => 'FO', 'name' => 'Front Office & Reservations', 'is_active' => '1'])->assertSessionHasNoErrors();
    delete(tenantUrl('sunrise', '/property/departments/'.$department->id))->assertRedirect(tenantUrl('sunrise', '/property/departments'));

    expect(PropertySetup::run(fn (): int => Department::query()->count()))->toBe(0)
        ->and(PropertySetup::run(fn (): ?string => Department::withTrashed()->value('name')))->toBe('Front Office & Reservations');
});

it('lets the right roles see and change the catalogue', function (): void {
    $department = PropertySetup::run(fn (): Department => Department::factory()->create());
    $amenity = PropertySetup::run(fn (): Amenity => Amenity::factory()->create());

    actingAs(PropertySetup::user(DefaultRole::Accountant));
    get(tenantUrl('sunrise', '/property/departments'))->assertOk();
    post(tenantUrl('sunrise', '/property/departments'), ['code' => 'X', 'name' => 'X'])->assertForbidden();
    delete(tenantUrl('sunrise', '/property/departments/'.$department->id))->assertForbidden();
    get(tenantUrl('sunrise', '/property/amenities'))->assertForbidden();

    actingAs(PropertySetup::user(DefaultRole::FrontOfficeManager));
    get(tenantUrl('sunrise', '/property/amenities'))->assertOk();
    put(tenantUrl('sunrise', '/property/amenities/'.$amenity->id), ['name' => 'X', 'category' => 'service'])->assertForbidden();

    actingAs(PropertySetup::user(DefaultRole::FrontDeskAgent));
    get(tenantUrl('sunrise', '/property/departments'))->assertForbidden();
});
