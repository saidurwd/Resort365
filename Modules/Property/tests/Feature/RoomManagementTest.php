<?php

use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Property\Contracts\RoomUsage;
use Modules\Property\Models\Cottage;
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
 * A cottage and a room type in the property.
 *
 * @return array{Cottage, RoomType}
 */
function cottageIn(string $code): array
{
    $propertyId = PropertySetup::property($code)->id;

    return PropertySetup::run(fn (): array => [
        Cottage::factory()->create(['property_id' => $propertyId, 'name' => 'Palm '.$code]),
        RoomType::factory()->create(['property_id' => $propertyId, 'max_adults' => 2, 'max_children' => 1, 'max_occupancy' => 3]),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function roomInput(Cottage $cottage, RoomType $roomType, array $overrides = []): array
{
    return ['number' => '401', 'cottage_id' => $cottage->id, 'room_type_id' => $roomType->id, 'floor' => '1', 'is_active' => '1', ...$overrides];
}

it('adds a room to a cottage with an occupancy override', function (): void {
    [$cottage, $roomType] = cottageIn('CXB');

    actingAs(PropertySetup::user(DefaultRole::GeneralManager, 'CXB'));
    withSession(PropertySetup::current('CXB'));

    get(tenantUrl('sunrise', '/property/rooms/create?cottage='.$cottage->id))->assertOk()->assertSee($cottage->name);
    post(tenantUrl('sunrise', '/property/rooms'), roomInput($cottage, $roomType, ['number' => 'a01', 'max_adults' => 1, 'max_children' => 0]))
        ->assertSessionHasNoErrors()->assertRedirect(tenantUrl('sunrise', '/property/cottages/'.$cottage->id));

    $room = PropertySetup::run(fn (): Room => Room::query()->where('number', 'A01')->sole());
    expect($room->cottage_id)->toBe($cottage->id)
        ->and($room->property_id)->toBe($cottage->property_id)
        ->and($room->max_adults)->toBe(1);

    get(tenantUrl('sunrise', '/property/cottages/'.$cottage->id))->assertOk()->assertSeeInOrder(['A01', '1 / 0']);
    get(tenantUrl('sunrise', '/property/rooms'))->assertOk();
});

it('keeps room numbers unique per property, including deleted rooms', function (): void {
    [$cottage, $roomType] = cottageIn('CXB');
    [$sylhetCottage, $sylhetType] = cottageIn('SYL');
    $deleted = PropertySetup::run(function () use ($cottage, $roomType): Room {
        Room::factory()->create(['property_id' => $cottage->property_id, 'cottage_id' => $cottage->id, 'room_type_id' => $roomType->id, 'number' => '401']);
        $room = Room::factory()->create(['property_id' => $cottage->property_id, 'cottage_id' => $cottage->id, 'room_type_id' => $roomType->id, 'number' => '402']);
        $room->delete();

        return $room;
    });

    actingAs(PropertySetup::user(DefaultRole::GeneralManager, 'CXB', 'SYL'));
    withSession(PropertySetup::current('CXB'));

    post(tenantUrl('sunrise', '/property/rooms'), roomInput($cottage, $roomType))->assertSessionHasErrors('number');
    post(tenantUrl('sunrise', '/property/rooms'), roomInput($cottage, $roomType, ['number' => $deleted->number]))
        ->assertSessionHasErrors(['number' => __('This room number is already used in this property (deleted rooms keep their numbers).')]);

    withSession(PropertySetup::current('SYL'));
    post(tenantUrl('sunrise', '/property/rooms'), roomInput($sylhetCottage, $sylhetType))->assertSessionHasNoErrors();
});

it('only puts a room in a cottage and a room type of its own property', function (): void {
    [$cottage, $roomType] = cottageIn('CXB');
    [$sylhetCottage, $sylhetType] = cottageIn('SYL');

    actingAs(PropertySetup::user(DefaultRole::GeneralManager, 'CXB', 'SYL'));
    withSession(PropertySetup::current('CXB'));

    post(tenantUrl('sunrise', '/property/rooms'), roomInput($sylhetCottage, $roomType))->assertSessionHasErrors('cottage_id');
    post(tenantUrl('sunrise', '/property/rooms'), roomInput($cottage, $sylhetType))->assertSessionHasErrors('room_type_id');
    post(tenantUrl('sunrise', '/property/rooms'), roomInput($cottage, $roomType, ['number' => '', 'max_adults' => 0]))->assertSessionHasErrors(['number', 'max_adults']);

    expect(PropertySetup::run(fn (): int => Room::query()->count()))->toBe(0);
});

it('edits a room and keeps it in its own property after switching', function (): void {
    [$cottage, $roomType] = cottageIn('SYL');
    $room = PropertySetup::run(fn (): Room => Room::factory()->create(['property_id' => $cottage->property_id, 'cottage_id' => $cottage->id, 'room_type_id' => $roomType->id, 'number' => '101']));

    actingAs(PropertySetup::user(DefaultRole::GeneralManager, 'CXB', 'SYL'));
    withSession(PropertySetup::current('CXB'));

    get(tenantUrl('sunrise', '/property/rooms/'.$room->id.'/edit'))->assertOk()->assertSee($cottage->name);
    put(tenantUrl('sunrise', '/property/rooms/'.$room->id), roomInput($cottage, $roomType, ['number' => '101', 'name' => 'Master bedroom', 'is_active' => '0']))
        ->assertSessionHasNoErrors();

    $room = PropertySetup::run(fn (): Room => $room->fresh() ?? throw new RuntimeException('Room missing'));
    expect($room->name)->toBe('Master bedroom')
        ->and($room->is_active)->toBeFalse()
        ->and($room->property_id)->toBe(PropertySetup::property('SYL')->id);
});

it('refuses to delete a room with future bookings', function (): void {
    [$cottage, $roomType] = cottageIn('CXB');
    [$booked, $free] = PropertySetup::run(fn (): array => [
        Room::factory()->create(['property_id' => $cottage->property_id, 'cottage_id' => $cottage->id, 'room_type_id' => $roomType->id, 'number' => '401']),
        Room::factory()->create(['property_id' => $cottage->property_id, 'cottage_id' => $cottage->id, 'room_type_id' => $roomType->id, 'number' => '402']),
    ]);

    // Stands in for the Reservation module's check (Step 1.6).
    app()->instance(RoomUsage::class, new readonly class($booked->id) implements RoomUsage
    {
        public function __construct(private int $bookedRoomId) {}

        public function hasFutureBookings(int $roomId): bool
        {
            return $roomId === $this->bookedRoomId;
        }
    });

    actingAs(PropertySetup::user(DefaultRole::GeneralManager, 'CXB'));

    delete(tenantUrl('sunrise', '/property/rooms/'.$booked->id))
        ->assertSessionHas('error', __('Room :number has future bookings. Move them first, or deactivate the room.', ['number' => '401']));
    delete(tenantUrl('sunrise', '/property/rooms/'.$free->id))->assertRedirect(tenantUrl('sunrise', '/property/cottages/'.$cottage->id));

    expect(PropertySetup::run(fn (): array => Room::query()->pluck('number')->all()))->toBe(['401']);
});

it('keeps room changes to managers', function (): void {
    [$cottage, $roomType] = cottageIn('CXB');
    $room = PropertySetup::run(fn (): Room => Room::factory()->create(['property_id' => $cottage->property_id, 'cottage_id' => $cottage->id, 'room_type_id' => $roomType->id]));

    actingAs(PropertySetup::user(DefaultRole::HousekeepingSupervisor, 'CXB'));
    withSession(PropertySetup::current('CXB'));

    get(tenantUrl('sunrise', '/property/rooms'))->assertOk();
    get(tenantUrl('sunrise', '/property/rooms/create'))->assertForbidden();
    get(tenantUrl('sunrise', '/property/rooms/'.$room->id.'/edit'))->assertForbidden();
    put(tenantUrl('sunrise', '/property/rooms/'.$room->id), roomInput($cottage, $roomType))->assertForbidden();
    delete(tenantUrl('sunrise', '/property/rooms/'.$room->id))->assertForbidden();
});

it('needs a current property for the setup screens', function (): void {
    actingAs(PropertySetup::user(DefaultRole::GeneralManager));

    get(tenantUrl('sunrise', '/property/rooms'))->assertForbidden();
    get(tenantUrl('sunrise', '/property/cottages/create'))->assertForbidden();
});
