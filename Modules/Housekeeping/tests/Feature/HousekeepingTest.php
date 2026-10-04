<?php

/*
| Housekeeping (Step 2.7). "Done when": checking out makes the room dirty and creates a cleaning
| task; an OOO block makes the room unavailable in the availability search.
|
| Rooms 401/402 (C04), 701–703 (C07), 801–803 (C08); 6,000 a night + SC 10% + VAT 15% = 7,590.00.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Contracts\FolioSettlement;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Services\DefaultChargeCodes;
use Modules\Core\Models\TaxCategory;
use Modules\FrontOffice\Actions\PostStayCharges;
use Modules\FrontOffice\Actions\RunNightAudit;
use Modules\Housekeeping\Enums\TaskStatus;
use Modules\Housekeeping\Enums\TaskType;
use Modules\Housekeeping\Models\HousekeepingTask;
use Modules\Housekeeping\Models\RoomBlock;
use Modules\Housekeeping\Models\RoomStatusLog;
use Modules\IAM\Models\Role;
use Modules\Property\Enums\HousekeepingStatus;
use Modules\Property\Models\Property;
use Modules\Property\Models\Room;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\DTOs\AvailabilitySearch;
use Modules\Reservation\DTOs\Occupancy;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Services\AvailabilityService;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(fn () => app(DefaultChargeCodes::class)->ensure(TaxCategory::query()->where('code', 'ROOM')->value('id')));
    Notification::fake();
});

function hkDay(): CarbonImmutable
{
    return CarbonImmutable::parse(booking(fn (): string => Property::query()->where('code', 'CXB')->sole()->business_date->toDateString()));
}

function hkRoomStatus(string $number): HousekeepingStatus
{
    return booking(fn (): HousekeepingStatus => Room::query()->where('number', $number)->sole()->housekeeping_status);
}

/**
 * A guest in house in the rooms, from D + $from for $nights nights.
 *
 * @param  list<string>  $units
 */
function hkStay(array $units, int $from, int $nights): Reservation
{
    $reservation = bookStay($units, hkDay()->addDays($from)->toDateString(), hkDay()->addDays($from + $nights)->toDateString(), ['depositPercent' => '0', 'allowDepositOverride' => true]);
    booking(fn () => app(StayOperations::class)->checkIn($reservation->id));

    return freshReservation($reservation->id);
}

/**
 * @return list<array{string, string, string}> room number, type, status
 */
function hkTasks(): array
{
    $rooms = booking(fn () => Room::query()->pluck('number', 'id'));

    return booking(fn (): array => HousekeepingTask::query()->orderBy('id')->get()
        ->map(fn (HousekeepingTask $task): array => [(string) $rooms[$task->room_id], $task->type->value, $task->status->value])->all());
}

it('makes the room dirty with a departure clean when the guest checks out', function (): void {
    staffUser();
    $stay = hkStay(['401'], -2, 2);
    booking(fn () => PostStayCharges::make()->handle($stay->id));
    $folio = booking(fn () => app(FolioSettlement::class)->folios($stay->id))[0];
    booking(fn () => RecordPayment::make()->handle(new NewPayment($stay->id, PaymentMethod::Cash, $folio->balance, folioId: $folio->id)));
    expect(hkRoomStatus('401'))->toBe(HousekeepingStatus::Clean);

    post(tenantUrl('sunrise', "/frontoffice/check-out/{$stay->id}"))->assertSessionHas('success');

    $task = booking(fn (): HousekeepingTask => HousekeepingTask::query()->sole());
    expect(hkRoomStatus('401'))->toBe(HousekeepingStatus::Dirty)
        ->and([$task->type, $task->status, $task->business_date->toDateString(), $task->reservation_id])->toBe([TaskType::Departure, TaskStatus::Pending, hkDay()->toDateString(), $stay->id])
        ->and(booking(fn () => RoomStatusLog::query()->sole()->reason))->toBe('Guest checked out');
});

it('cleans, inspects and fails a room through the task steps', function (): void {
    staffUser(DefaultRole::HousekeepingSupervisor);
    booking(fn () => Room::query()->where('number', '402')->update(['housekeeping_status' => 'dirty']));
    $task = booking(fn (): HousekeepingTask => HousekeepingTask::factory()->create(['room_id' => bookingIds()['rooms']['402'], 'business_date' => hkDay()->toDateString()]));
    $supervisor = auth()->user();

    get(tenantUrl('sunrise', '/housekeeping/tasks'))->assertOk()->assertSeeHtml('data-task="'.$task->id.'"');
    post(tenantUrl('sunrise', '/housekeeping/tasks/assign'), ['task_ids' => [$task->id], 'attendant_id' => $supervisor->id])->assertSessionHas('success');

    post(tenantUrl('sunrise', "/housekeeping/tasks/{$task->id}/start"))->assertSessionHas('success');
    post(tenantUrl('sunrise', "/housekeeping/tasks/{$task->id}/pass"))->assertSessionHas('error');
    post(tenantUrl('sunrise', "/housekeeping/tasks/{$task->id}/finish"))->assertSessionHas('success');
    expect(hkRoomStatus('402'))->toBe(HousekeepingStatus::Clean);

    post(tenantUrl('sunrise', "/housekeeping/tasks/{$task->id}/fail"), ['note' => 'Hair in the shower'])->assertSessionHas('success');
    expect(hkRoomStatus('402'))->toBe(HousekeepingStatus::Dirty)
        ->and(booking(fn (): ?TaskStatus => $task->fresh()?->status))->toBe(TaskStatus::Pending);

    post(tenantUrl('sunrise', "/housekeeping/tasks/{$task->id}/finish"));
    post(tenantUrl('sunrise', "/housekeeping/tasks/{$task->id}/pass"))->assertSessionHas('success');

    $done = booking(fn (): HousekeepingTask => $task->fresh());
    expect(hkRoomStatus('402'))->toBe(HousekeepingStatus::Inspected)
        ->and([$done->status, $done->assigned_to, $done->inspected_by, $done->notes])->toBe([TaskStatus::Inspected, $supervisor->id, $supervisor->id, 'Hair in the shower'])
        ->and(booking(fn () => RoomStatusLog::query()->where('room_id', bookingIds()['rooms']['402'])->pluck('to_status')->map->value->all()))
        ->toBe(['clean', 'dirty', 'clean', 'inspected']);
});

it('lets attendants work only their own or unassigned tasks, and only supervisors inspect', function (): void {
    $other = tenantUserAs(tenant('sunrise'), DefaultRole::HousekeepingSupervisor);
    $mine = booking(fn (): HousekeepingTask => HousekeepingTask::factory()->create(['room_id' => bookingIds()['rooms']['401'], 'business_date' => hkDay()->toDateString()]));
    $theirs = booking(fn (): HousekeepingTask => HousekeepingTask::factory()->create(['room_id' => bookingIds()['rooms']['402'], 'business_date' => hkDay()->toDateString(), 'assigned_to' => $other->id]));

    // An attendant: a custom role with housekeeping.task.perform only.
    $attendant = staffUser(DefaultRole::FrontDeskAgent);
    $role = booking(fn () => Role::query()->create(['name' => 'Attendant', 'guard_name' => 'web']));
    booking(fn () => $role->givePermissionTo('housekeeping.task.perform'));
    booking(fn () => $attendant->syncRoles([$role]));
    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

    get(tenantUrl('sunrise', '/housekeeping/my-tasks'))->assertOk()->assertSeeHtml('data-my-task="'.$mine->id.'"')->assertDontSeeHtml('data-my-task="'.$theirs->id.'"');
    get(tenantUrl('sunrise', '/housekeeping/tasks'))->assertForbidden();
    post(tenantUrl('sunrise', "/housekeeping/tasks/{$theirs->id}/start"))->assertForbidden();
    post(tenantUrl('sunrise', "/housekeeping/tasks/{$mine->id}/finish"))->assertSessionHas('success');
    post(tenantUrl('sunrise', "/housekeeping/tasks/{$mine->id}/pass"))->assertForbidden();
    post(tenantUrl('sunrise', '/housekeeping/tasks/assign'), ['task_ids' => [$mine->id]])->assertForbidden();
});

it('puts rooms in house on the daily round after the night audit, once', function (): void {
    staffUser(DefaultRole::HousekeepingSupervisor);
    hkStay(['401'], -1, 3);
    hkStay(['801'], 0, 2);

    booking(fn () => RunNightAudit::make()->handle(bookingIds()['property']));
    $next = hkDay()->toDateString();

    expect(hkTasks())->toBe([['401', 'stayover', 'pending'], ['801', 'stayover', 'pending']])
        ->and(hkRoomStatus('401'))->toBe(HousekeepingStatus::Dirty)
        ->and(booking(fn () => HousekeepingTask::query()->pluck('business_date')->map->toDateString()->unique()->all()))->toBe([$next]);

    post(tenantUrl('sunrise', '/housekeeping/tasks/generate'))->assertSessionHas('success');
    expect(hkTasks())->toHaveCount(2);
});

it('makes the room a guest moved out of dirty', function (): void {
    staffUser();
    $stay = hkStay(['401'], -1, 3);

    booking(fn () => app(StayOperations::class)->moveRoom($stay->items->sole()->id, bookingIds()['rooms']['402']));

    expect(hkRoomStatus('401'))->toBe(HousekeepingStatus::Dirty)->and(hkTasks())->toBe([['401', 'departure', 'pending']]);
});

it('shows the board and sets rooms in bulk', function (): void {
    staffUser(DefaultRole::HousekeepingSupervisor);
    hkStay(['401'], -1, 3);
    $arriving = bookStay(['801'], hkDay()->toDateString(), hkDay()->addDay()->toDateString());

    get(tenantUrl('sunrise', '/housekeeping/board'))->assertOk()
        ->assertSeeHtmlInOrder(['data-room-tile="401"', 'data-occupied', 'data-room-tile="402"'])
        ->assertSeeHtmlInOrder(['data-room-tile="801"', 'data-arriving'])
        ->assertSee($arriving->code === '' ? '' : 'Arriving');

    post(tenantUrl('sunrise', '/housekeeping/board/status'), ['room_ids' => [bookingIds()['rooms']['701'], bookingIds()['rooms']['702']], 'status' => 'dirty'])->assertSessionHas('success');
    expect([hkRoomStatus('701'), hkRoomStatus('702'), hkRoomStatus('703')])->toBe([HousekeepingStatus::Dirty, HousekeepingStatus::Dirty, HousekeepingStatus::Clean]);

    post(tenantUrl('sunrise', '/housekeeping/board/status'), ['room_ids' => [999999], 'status' => 'dirty'])->assertSessionHasErrors('room_ids.0');

    staffUser(DefaultRole::FrontDeskAgent);
    get(tenantUrl('sunrise', '/housekeeping/board'))->assertOk()->assertDontSeeHtml('name="room_ids[]"');
    post(tenantUrl('sunrise', '/housekeeping/board/status'), ['room_ids' => [bookingIds()['rooms']['703']], 'status' => 'dirty'])->assertForbidden();

    staffUser(DefaultRole::Accountant);
    get(tenantUrl('sunrise', '/housekeeping/board'))->assertForbidden();
});

it('takes a room out of order so it cannot be sold, refuses booked nights and puts it back', function (): void {
    staffUser(DefaultRole::HousekeepingSupervisor);
    $day = hkDay();
    $ids = bookingIds();
    $search = new AvailabilitySearch($ids['property'], $ids['plan'], $day->addDays(2), $day->addDays(4), new Occupancy(2, 0));
    $booked = bookStay(['402'], $day->addDays(3)->toDateString(), $day->addDays(5)->toDateString());

    post(tenantUrl('sunrise', '/housekeeping/blocks'), ['room_id' => $ids['rooms']['401'], 'type' => 'out_of_order', 'from_date' => $day->addDays(2)->toDateString(),
        'to_date' => $day->addDays(4)->toDateString(), 'reason' => 'Broken AC'])->assertSessionHas('success');

    expect(booking(fn (): array => app(AvailabilityService::class)->lockedRoomIds($search)))->toContain($ids['rooms']['401']);
    get(tenantUrl('sunrise', '/housekeeping/blocks'))->assertOk()->assertSee('Broken AC');

    // A booked room is refused, naming the booking; nothing is saved.
    post(tenantUrl('sunrise', '/housekeeping/blocks'), ['room_id' => $ids['rooms']['402'], 'type' => 'out_of_order', 'from_date' => $day->addDays(2)->toDateString(),
        'to_date' => $day->addDays(4)->toDateString(), 'reason' => 'Paint'])->assertSessionHas('error', fn (string $message): bool => str_contains($message, $booked->code));
    expect(booking(fn (): int => RoomBlock::query()->count()))->toBe(1);

    // Out of service only flags the room: it stays sellable.
    post(tenantUrl('sunrise', '/housekeeping/blocks'), ['room_id' => $ids['rooms']['701'], 'type' => 'out_of_service', 'from_date' => $day->addDays(2)->toDateString(),
        'to_date' => $day->addDays(4)->toDateString(), 'reason' => 'Curtains replaced'])->assertSessionHas('success');
    expect(booking(fn (): array => app(AvailabilityService::class)->lockedRoomIds($search)))->not->toContain($ids['rooms']['701']);

    $block = booking(fn () => RoomBlock::query()->where('room_id', $ids['rooms']['401'])->sole());
    post(tenantUrl('sunrise', "/housekeeping/blocks/{$block->id}/end"))->assertSessionHas('success');
    expect(booking(fn (): array => app(AvailabilityService::class)->lockedRoomIds($search)))->not->toContain($ids['rooms']['401'])
        ->and(booking(fn (): ?string => $block->fresh()?->status->value))->toBe('ended');

    post(tenantUrl('sunrise', '/housekeeping/blocks'), ['room_id' => $ids['rooms']['401'], 'type' => 'out_of_order', 'from_date' => $day->toDateString(),
        'to_date' => $day->toDateString(), 'reason' => 'x'])->assertSessionHasErrors('to_date');

    staffUser(DefaultRole::FrontDeskAgent);
    get(tenantUrl('sunrise', '/housekeeping/blocks'))->assertForbidden();
});
