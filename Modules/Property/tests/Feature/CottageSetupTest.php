<?php

/*
| Step 1.1 "Done when": the demo resort has single-room and multi-room cottages set up through
| the UI; the cottage page lists its rooms and total occupancy.
*/

use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Activity;
use Modules\Property\Enums\BookingMode;
use Modules\Property\Models\Amenity;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\CottageType;
use Modules\Property\Models\Room;
use Modules\Property\Models\RoomType;
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

/**
 * A room type and a cottage type in the property, created directly.
 *
 * @return array{RoomType, CottageType}
 */
function setupTypes(string $code = 'CXB'): array
{
    $propertyId = PropertySetup::property($code)->id;

    return PropertySetup::run(fn (): array => [
        RoomType::factory()->create(['property_id' => $propertyId, 'code' => 'DK', 'name' => 'Deluxe King', 'max_adults' => 2, 'max_children' => 1, 'max_occupancy' => 3]),
        CottageType::factory()->create(['property_id' => $propertyId, 'code' => 'GC', 'name' => 'Garden Cottage']),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function quickCottage(CottageType $cottageType, RoomType $roomType, array $overrides = []): array
{
    return [
        'code' => 'c04', 'name' => 'Palm', 'cottage_type_id' => $cottageType->id, 'zone' => 'Garden',
        'booking_mode' => 'both', 'status' => 'active', 'room_count' => 3, 'room_type_id' => $roomType->id,
        'first_room_number' => '401', 'floor' => '0', ...$overrides,
    ];
}

it('sets up single-room and multi-room cottages through the UI and shows rooms with total occupancy', function (): void {
    actingAs(PropertySetup::user(DefaultRole::GeneralManager, 'CXB'));
    withSession(PropertySetup::current('CXB'));

    post(tenantUrl('sunrise', '/property/room-types'), [
        'code' => 'dk', 'name' => 'Deluxe King', 'base_occupancy' => 2, 'max_adults' => 2, 'max_children' => 1, 'max_occupancy' => 3,
    ])->assertSessionHasNoErrors();
    post(tenantUrl('sunrise', '/property/room-types'), [
        'code' => 'hs', 'name' => 'Honeymoon Suite', 'base_occupancy' => 2, 'max_adults' => 2, 'max_children' => 0, 'max_occupancy' => 2,
    ])->assertSessionHasNoErrors();
    post(tenantUrl('sunrise', '/property/cottage-types'), ['code' => 'hc', 'name' => 'Honeymoon Cottage', 'bedrooms' => 1, 'max_occupancy' => 2])->assertSessionHasNoErrors();
    post(tenantUrl('sunrise', '/property/cottage-types'), ['code' => 'gc', 'name' => 'Garden Cottage', 'bedrooms' => 3, 'max_occupancy' => 9])->assertSessionHasNoErrors();

    [$dk, $hs, $hc, $gc] = PropertySetup::run(fn (): array => [
        RoomType::query()->where('code', 'DK')->sole(), RoomType::query()->where('code', 'HS')->sole(),
        CottageType::query()->where('code', 'HC')->sole(), CottageType::query()->where('code', 'GC')->sole(),
    ]);

    get(tenantUrl('sunrise', '/property/cottages/create'))->assertOk()->assertSee(__('Number of rooms'));
    post(tenantUrl('sunrise', '/property/cottages'), quickCottage($hc, $hs, ['code' => 'c01', 'name' => 'Coral', 'room_count' => 1, 'first_room_number' => '101']))
        ->assertSessionHasNoErrors();
    post(tenantUrl('sunrise', '/property/cottages'), quickCottage($gc, $dk))->assertSessionHasNoErrors();

    [$coral, $palm] = PropertySetup::run(fn (): array => [Cottage::query()->where('code', 'C01')->sole(), Cottage::query()->where('code', 'C04')->sole()]);

    expect(PropertySetup::run(fn (): array => Room::query()->where('cottage_id', $palm->id)->orderBy('number')->pluck('number')->all()))->toBe(['401', '402', '403'])
        ->and($palm->property_id)->toBe(PropertySetup::property('CXB')->id);

    get(tenantUrl('sunrise', '/property/cottages/'.$coral->id))->assertOk()->assertSeeInOrder(['Coral', '101', 'Honeymoon Suite']);

    $page = get(tenantUrl('sunrise', '/property/cottages/'.$palm->id))->assertOk()
        ->assertSeeInOrder(['401', 'Deluxe King', '402', '403', __('Total (active rooms)')]);
    expect($page->getContent())->toMatch('/data-rooms-occupancy>\s*9\s*</')
        ->toMatch('/data-total-occupancy[^>]*>.*?<h3>\s*9\s*<\/h3>/s');
});

it('lists the current property\'s cottages and rooms in the DataTables', function (): void {
    [$roomType, $cottageType] = setupTypes('CXB');
    [$sylhetRoomType, $sylhetCottageType] = setupTypes('SYL');
    PropertySetup::run(function () use ($roomType, $cottageType, $sylhetRoomType, $sylhetCottageType): void {
        $cottage = Cottage::factory()->create(['property_id' => $cottageType->property_id, 'cottage_type_id' => $cottageType->id, 'name' => 'Palm']);
        Room::factory()->create(['property_id' => $cottage->property_id, 'cottage_id' => $cottage->id, 'room_type_id' => $roomType->id, 'number' => '401']);
        $other = Cottage::factory()->create(['property_id' => $sylhetCottageType->property_id, 'cottage_type_id' => $sylhetCottageType->id, 'name' => 'Malnicherra']);
        Room::factory()->create(['property_id' => $other->property_id, 'cottage_id' => $other->id, 'room_type_id' => $sylhetRoomType->id, 'number' => '901']);
    });

    actingAs(PropertySetup::user(DefaultRole::FrontDeskAgent, 'CXB', 'SYL'));
    withSession(PropertySetup::current('CXB'));

    get(tenantUrl('sunrise', '/property/cottages'))->assertOk()->assertSee("Sunrise Cox's Bazar");
    $cottages = get(tenantUrl('sunrise', '/property/cottages/data?draw=1&start=0&length=25'))->assertOk()->json('data');
    $rooms = get(tenantUrl('sunrise', '/property/rooms/data?draw=1&start=0&length=25'))->assertOk()->json('data');

    expect($cottages)->toHaveCount(1)
        ->and($cottages[0]['name'])->toContain('Palm')
        ->and($cottages[0]['max_occupancy'])->toBe(3)
        ->and($rooms)->toHaveCount(1)
        ->and($rooms[0]['number'])->toContain('401');

    withSession(PropertySetup::current('SYL'));
    expect(get(tenantUrl('sunrise', '/property/cottages/data?draw=1&start=0&length=25'))->json('data.0.name'))->toContain('Malnicherra');
});

it('validates the quick form: taken room numbers, types of another property, numbers without digits', function (): void {
    [$roomType, $cottageType] = setupTypes('CXB');
    [$sylhetRoomType] = setupTypes('SYL');
    PropertySetup::run(fn () => Room::factory()->create(['property_id' => $roomType->property_id, 'room_type_id' => $roomType->id, 'number' => '402']));

    actingAs(PropertySetup::user(DefaultRole::GeneralManager, 'CXB', 'SYL'));
    withSession(PropertySetup::current('CXB'));

    post(tenantUrl('sunrise', '/property/cottages'), quickCottage($cottageType, $roomType))
        ->assertSessionHasErrors(['first_room_number' => __('These room numbers are already used in this property: :numbers.', ['numbers' => '402'])]);
    post(tenantUrl('sunrise', '/property/cottages'), quickCottage($cottageType, $sylhetRoomType, ['first_room_number' => '501']))->assertSessionHasErrors('room_type_id');
    post(tenantUrl('sunrise', '/property/cottages'), quickCottage($cottageType, $roomType, ['first_room_number' => 'PH']))->assertSessionHasErrors('first_room_number');
    post(tenantUrl('sunrise', '/property/cottages'), quickCottage($cottageType, $roomType, ['room_count' => 0, 'booking_mode' => 'sometimes']))
        ->assertSessionHasErrors(['room_count', 'booking_mode']);

    expect(PropertySetup::run(fn (): bool => Cottage::query()->where('code', 'C04')->exists()))->toBeFalse();

    // The same numbers are fine in another property.
    withSession(PropertySetup::current('SYL'));
    [, $sylhetCottageType] = PropertySetup::run(fn (): array => [null, CottageType::query()->where('property_id', $sylhetRoomType->property_id)->sole()]);
    post(tenantUrl('sunrise', '/property/cottages'), quickCottage($sylhetCottageType, $sylhetRoomType))->assertSessionHasNoErrors();
});

it('keeps cottage and room setup to managers', function (): void {
    [$roomType, $cottageType] = setupTypes('CXB');
    $cottage = PropertySetup::run(fn (): Cottage => Cottage::factory()->create(['property_id' => $cottageType->property_id, 'cottage_type_id' => $cottageType->id]));

    actingAs(PropertySetup::user(DefaultRole::FrontDeskAgent, 'CXB'));
    withSession(PropertySetup::current('CXB'));

    get(tenantUrl('sunrise', '/property/cottages/'.$cottage->id))->assertOk()->assertDontSee(__('Add room'));
    get(tenantUrl('sunrise', '/property/cottage-types'))->assertOk();
    get(tenantUrl('sunrise', '/property/cottages/create'))->assertForbidden();
    post(tenantUrl('sunrise', '/property/cottages'), quickCottage($cottageType, $roomType))->assertForbidden();
    put(tenantUrl('sunrise', '/property/cottages/'.$cottage->id), [])->assertForbidden();
    delete(tenantUrl('sunrise', '/property/cottages/'.$cottage->id))->assertForbidden();
    post(tenantUrl('sunrise', '/property/rooms'), [])->assertForbidden();
    post(tenantUrl('sunrise', '/property/room-types'), [])->assertForbidden();
    post(tenantUrl('sunrise', '/property/cottage-types'), [])->assertForbidden();

    actingAs(PropertySetup::user(DefaultRole::Accountant, 'CXB'));
    get(tenantUrl('sunrise', '/property/cottages'))->assertForbidden();
    get(tenantUrl('sunrise', '/property/rooms'))->assertForbidden();
});

it('hides cottages of a property the user cannot access', function (): void {
    [, $cottageType] = setupTypes('SYL');
    $cottage = PropertySetup::run(fn (): Cottage => Cottage::factory()->create(['property_id' => $cottageType->property_id, 'cottage_type_id' => $cottageType->id]));

    actingAs(PropertySetup::user(DefaultRole::GeneralManager, 'CXB'));

    get(tenantUrl('sunrise', '/property/cottages/'.$cottage->id))->assertNotFound();
    put(tenantUrl('sunrise', '/property/cottages/'.$cottage->id), [])->assertNotFound();
    get(tenantUrl('sunrise', '/property/cottage-types/'.$cottageType->id.'/edit'))->assertNotFound();
});

it('changes a cottage and records it in the audit log', function (): void {
    [, $cottageType] = setupTypes('CXB');
    $cottage = PropertySetup::run(fn (): Cottage => Cottage::factory()->create(['property_id' => $cottageType->property_id, 'cottage_type_id' => $cottageType->id, 'code' => 'C07']));

    actingAs(PropertySetup::user(DefaultRole::GeneralManager, 'CXB'));
    withSession(PropertySetup::current('CXB'));

    get(tenantUrl('sunrise', '/property/cottages/'.$cottage->id.'/edit'))->assertOk();
    put(tenantUrl('sunrise', '/property/cottages/'.$cottage->id), [
        'code' => 'c07', 'name' => 'Lagoon Villa', 'cottage_type_id' => $cottageType->id, 'booking_mode' => 'whole_only',
        'status' => 'active', 'max_occupancy_override' => 10,
    ])->assertSessionHasNoErrors()->assertRedirect(tenantUrl('sunrise', '/property/cottages/'.$cottage->id));

    $cottage = PropertySetup::run(fn (): Cottage => $cottage->fresh() ?? throw new RuntimeException('Cottage missing'));
    expect($cottage->booking_mode)->toBe(BookingMode::WholeOnly)
        ->and($cottage->max_occupancy_override)->toBe(10)
        ->and(PropertySetup::run(fn (): bool => Activity::query()->where('subject_type', 'cottage')->where('subject_id', $cottage->id)->where('event', 'updated')->exists()))->toBeTrue();
});

it('deletes a cottage only when it has no rooms', function (): void {
    [$roomType, $cottageType] = setupTypes('CXB');
    [$withRooms, $empty] = PropertySetup::run(function () use ($roomType, $cottageType): array {
        $withRooms = Cottage::factory()->create(['property_id' => $cottageType->property_id, 'cottage_type_id' => $cottageType->id, 'name' => 'Palm']);
        Room::factory()->create(['property_id' => $withRooms->property_id, 'cottage_id' => $withRooms->id, 'room_type_id' => $roomType->id]);

        return [$withRooms, Cottage::factory()->create(['property_id' => $cottageType->property_id, 'cottage_type_id' => $cottageType->id])];
    });

    actingAs(PropertySetup::user(DefaultRole::GeneralManager, 'CXB'));

    delete(tenantUrl('sunrise', '/property/cottages/'.$withRooms->id))->assertSessionHas('error', __('Cottage ":name" still has rooms. Delete or move them first, or deactivate the cottage.', ['name' => 'Palm']));
    delete(tenantUrl('sunrise', '/property/cottages/'.$empty->id))->assertRedirect(tenantUrl('sunrise', '/property/cottages'));

    expect(PropertySetup::run(fn (): array => Cottage::query()->pluck('id')->all()))->toBe([$withRooms->id])
        ->and(PropertySetup::run(fn (): bool => Cottage::withTrashed()->whereKey($empty->id)->exists()))->toBeTrue()
        ->and(PropertySetup::run(fn (): bool => Activity::query()->where('subject_type', 'cottage')->where('subject_id', $empty->id)->where('event', 'deleted')->exists()))->toBeTrue();

    // A soft delete is logged as a "deleted" entry, so deleted_at never shows up as a change.
    $created = PropertySetup::run(fn (): array => (array) Activity::query()->where('subject_type', 'cottage')->where('subject_id', $empty->id)
        ->where('event', 'created')->sole()->attribute_changes?->get('attributes'));
    expect(array_key_exists('name', $created))->toBeTrue()
        ->and(array_key_exists('deleted_at', $created))->toBeFalse();
});

it('creates cottage and room types with amenities, and refuses to delete types in use', function (): void {
    $wifi = PropertySetup::run(fn (): Amenity => Amenity::factory()->create(['name' => 'Wi-Fi']));

    actingAs(PropertySetup::user(DefaultRole::GeneralManager, 'CXB'));
    withSession(PropertySetup::current('CXB'));

    post(tenantUrl('sunrise', '/property/cottage-types'), ['code' => 'fv', 'name' => 'Family Villa', 'bedrooms' => 3, 'max_occupancy' => 11, 'amenity_ids' => [$wifi->id]])
        ->assertSessionHasNoErrors();
    post(tenantUrl('sunrise', '/property/cottage-types'), ['code' => 'FV', 'name' => 'Copy', 'bedrooms' => 1, 'max_occupancy' => 2])->assertSessionHasErrors('code');
    post(tenantUrl('sunrise', '/property/room-types'), [
        'code' => 'fs', 'name' => 'Family Suite', 'base_occupancy' => 2, 'max_adults' => 2, 'max_children' => 1, 'max_occupancy' => 5,
    ])->assertSessionHasErrors(['max_occupancy' => __('Max occupancy cannot be more than max adults plus max children.')]);

    $villa = PropertySetup::run(fn (): CottageType => CottageType::query()->where('code', 'FV')->sole());
    expect(PropertySetup::run(fn (): array => $villa->amenities()->pluck('name')->all()))->toBe(['Wi-Fi'])
        ->and($villa->property_id)->toBe(PropertySetup::property('CXB')->id);

    get(tenantUrl('sunrise', '/property/cottage-types'))->assertOk()->assertSeeInOrder(['Family Villa', 'Wi-Fi']);
    get(tenantUrl('sunrise', '/property/cottage-types/'.$villa->id.'/edit'))->assertOk()->assertSee(__('Photos'));

    // The same code is fine in another property.
    withSession(PropertySetup::current('SYL'));
    actingAs(PropertySetup::user(DefaultRole::GeneralManager, 'CXB', 'SYL'));
    withSession(PropertySetup::current('SYL'));
    post(tenantUrl('sunrise', '/property/cottage-types'), ['code' => 'FV', 'name' => 'Hill Villa', 'bedrooms' => 3, 'max_occupancy' => 9])->assertSessionHasNoErrors();

    PropertySetup::run(fn () => Cottage::factory()->create(['property_id' => $villa->property_id, 'cottage_type_id' => $villa->id]));
    delete(tenantUrl('sunrise', '/property/cottage-types/'.$villa->id))->assertSessionHas('error');

    [$roomType] = setupTypes('CXB');
    delete(tenantUrl('sunrise', '/property/room-types/'.$roomType->id))->assertRedirect(tenantUrl('sunrise', '/property/room-types'));
    expect(PropertySetup::run(fn (): bool => RoomType::query()->whereKey($roomType->id)->exists()))->toBeFalse();
});
