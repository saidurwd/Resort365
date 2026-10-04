<?php

/*
| Maintenance work orders, preventive schedules and lost & found (Step 2.7).
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Housekeeping\Enums\LostItemStatus;
use Modules\Housekeeping\Enums\WorkOrderStatus;
use Modules\Housekeeping\Jobs\RunPreventiveMaintenance;
use Modules\Housekeeping\Models\LostFoundItem;
use Modules\Housekeeping\Models\MaintenanceRequest;
use Modules\Housekeeping\Models\MaintenanceSchedule;
use Modules\Property\Models\Property;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
});

function mtDay(): string
{
    return booking(fn (): string => Property::query()->where('code', 'CXB')->sole()->business_date->toDateString());
}

it('lets any staff member report a fault and a technician close it', function (): void {
    $waiter = staffUser(DefaultRole::Waiter);
    get(tenantUrl('sunrise', '/housekeeping/work-orders/new'))->assertOk();
    get(tenantUrl('sunrise', '/housekeeping/work-orders'))->assertForbidden();

    post(tenantUrl('sunrise', '/housekeeping/work-orders'), ['title' => 'AC not cooling', 'category' => 'air_conditioning', 'priority' => 'urgent'])
        ->assertSessionHasErrors('location');
    post(tenantUrl('sunrise', '/housekeeping/work-orders'), ['title' => 'AC not cooling', 'category' => 'air_conditioning', 'priority' => 'urgent',
        'room_id' => bookingIds()['rooms']['401'], 'description' => 'Guest says it blows warm air.'])->assertSessionHas('success');

    $order = booking(fn (): MaintenanceRequest => MaintenanceRequest::query()->sole());
    expect([$order->status, $order->reported_by])->toBe([WorkOrderStatus::Open, $waiter->id]);
    get(tenantUrl('sunrise', "/housekeeping/work-orders/{$order->id}"))->assertOk()->assertDontSeeHtml('data-update-work-order');
    put(tenantUrl('sunrise', "/housekeeping/work-orders/{$order->id}"), ['status' => 'done', 'priority' => 'urgent', 'labour_cost' => '0', 'parts_cost' => '0'])->assertForbidden();

    $technician = staffUser(DefaultRole::MaintenanceTechnician);
    getJson(tenantUrl('sunrise', '/housekeeping/work-orders/data?draw=1&start=0&length=10'))->assertOk()->assertJsonPath('recordsTotal', 1);
    get(tenantUrl('sunrise', "/housekeeping/work-orders/{$order->id}"))->assertOk()->assertSeeHtml('data-update-work-order');

    $form = ['priority' => 'urgent', 'assigned_to' => $technician->id, 'labour_cost' => '500', 'parts_cost' => '1200.50'];
    put(tenantUrl('sunrise', "/housekeeping/work-orders/{$order->id}"), $form + ['status' => 'in_progress'])->assertSessionHas('success');
    put(tenantUrl('sunrise', "/housekeeping/work-orders/{$order->id}"), $form + ['status' => 'done'])->assertSessionHas('error');
    put(tenantUrl('sunrise', "/housekeeping/work-orders/{$order->id}"), $form + ['status' => 'done', 'resolution' => 'Gas refilled, filter cleaned.'])->assertSessionHas('success');

    $closed = booking(fn (): MaintenanceRequest => $order->fresh());
    expect([$closed->status, $closed->assigned_to, $closed->labour_cost, $closed->parts_cost])->toBe([WorkOrderStatus::Done, $technician->id, '500.00', '1200.50'])
        ->and($closed->started_at)->not->toBeNull()->and($closed->completed_at)->not->toBeNull();
    put(tenantUrl('sunrise', "/housekeeping/work-orders/{$order->id}"), $form + ['status' => 'open'])->assertSessionHas('error');

    // The reporter follows their own report.
    actingAs($waiter);
    get(tenantUrl('sunrise', "/housekeeping/work-orders/{$order->id}"))->assertOk()->assertSee('Gas refilled');
});

it('opens preventive work orders when they are due, once, and moves the schedule on', function (): void {
    staffUser(DefaultRole::MaintenanceTechnician);
    post(tenantUrl('sunrise', '/housekeeping/schedules'), ['title' => 'AC servicing', 'category' => 'air_conditioning', 'location' => 'All rooms',
        'interval_days' => 90, 'next_due_on' => mtDay(), 'is_active' => '1'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/housekeeping/schedules'), ['title' => 'Generator check', 'category' => 'electrical', 'location' => 'Generator',
        'interval_days' => 30, 'next_due_on' => now()->addDays(10)->toDateString(), 'is_active' => '1'])->assertSessionHas('success');

    expect(RunPreventiveMaintenance::dispatchSync())->toBe(1);
    artisan('housekeeping:preventive')->expectsOutputToContain('Preventive work orders opened: 0.')->assertSuccessful();
    post(tenantUrl('sunrise', '/housekeeping/schedules/run'))->assertSessionHas('success');

    $order = booking(fn (): MaintenanceRequest => MaintenanceRequest::query()->sole());
    $schedule = booking(fn (): MaintenanceSchedule => MaintenanceSchedule::query()->where('title', 'AC servicing')->sole());
    expect([$order->title, $order->due_on?->toDateString(), $order->maintenance_schedule_id])->toBe(['AC servicing', mtDay(), $schedule->id])
        ->and($schedule->next_due_on->toDateString())->toBe(CarbonImmutable::parse(mtDay())->addDays(90)->toDateString());

    get(tenantUrl('sunrise', '/housekeeping/schedules'))->assertOk()->assertSeeHtml('data-schedule="'.$schedule->id.'"');
    get(tenantUrl('sunrise', "/housekeeping/schedules/{$schedule->id}/edit"))->assertOk();
    put(tenantUrl('sunrise', "/housekeeping/schedules/{$schedule->id}"), ['title' => 'AC servicing', 'category' => 'air_conditioning', 'location' => 'All rooms',
        'interval_days' => 0, 'next_due_on' => mtDay()])->assertSessionHasErrors('interval_days');

    staffUser(DefaultRole::HousekeepingSupervisor);
    get(tenantUrl('sunrise', '/housekeeping/schedules'))->assertForbidden();
});

it('logs found items and returns them to a guest or disposes of them', function (): void {
    staffUser(DefaultRole::FrontDeskAgent);
    post(tenantUrl('sunrise', '/housekeeping/lost-found'), ['description' => 'Black sunglasses', 'found_on' => now()->toDateString(), 'found_at' => 'Pool deck',
        'stored_at' => 'Front office safe'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/housekeeping/lost-found'), ['description' => 'Phone charger', 'found_on' => now()->toDateString(), 'found_at' => 'Wardrobe',
        'room_id' => bookingIds()['rooms']['401']])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/housekeeping/lost-found'), ['found_on' => now()->addDay()->toDateString()])->assertSessionHasErrors(['description', 'found_at', 'found_on']);

    [$glasses, $charger] = booking(fn () => LostFoundItem::query()->orderBy('id')->get()->all());
    get(tenantUrl('sunrise', '/housekeeping/lost-found'))->assertOk();
    getJson(tenantUrl('sunrise', '/housekeeping/lost-found/data?draw=1&start=0&length=10'))->assertOk()->assertJsonPath('recordsTotal', 2);

    post(tenantUrl('sunrise', "/housekeeping/lost-found/{$glasses->id}/close"), ['outcome' => 'claimed'])->assertSessionHas('error');
    post(tenantUrl('sunrise', "/housekeeping/lost-found/{$glasses->id}/close"), ['outcome' => 'claimed', 'guest_id' => bookingIds()['guest']])->assertSessionHas('success');
    post(tenantUrl('sunrise', "/housekeeping/lost-found/{$charger->id}/close"), ['outcome' => 'disposed', 'notes' => 'Unclaimed after 90 days'])->assertSessionHas('success');
    post(tenantUrl('sunrise', "/housekeeping/lost-found/{$charger->id}/close"), ['outcome' => 'claimed', 'claimed_by_name' => 'X'])->assertSessionHas('error');

    expect(booking(fn () => [$glasses->fresh()?->status, $glasses->fresh()?->guest_id, $charger->fresh()?->status]))
        ->toBe([LostItemStatus::Claimed, bookingIds()['guest'], LostItemStatus::Disposed]);

    staffUser(DefaultRole::Chef);
    get(tenantUrl('sunrise', '/housekeeping/lost-found'))->assertForbidden();
});

it('does not show another tenant\'s work order or lost item', function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley'])));
    [$order, $item] = booking(fn (): array => [MaintenanceRequest::factory()->create(), LostFoundItem::factory()->create()], 'greenvalley');
    staffUser(DefaultRole::GeneralManager);

    get(tenantUrl('sunrise', "/housekeeping/work-orders/{$order->id}"))->assertNotFound();
    post(tenantUrl('sunrise', "/housekeeping/lost-found/{$item->id}/close"), ['outcome' => 'disposed'])->assertNotFound();
});
