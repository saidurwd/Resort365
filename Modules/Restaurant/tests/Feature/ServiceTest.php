<?php

/*
| Room service, deliveries, staff meals and table reservations (Step 3.8).
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Modules\Billing\Services\DefaultChargeCodes;
use Modules\Core\Models\TaxCategory;
use Modules\IAM\Models\User;
use Modules\Restaurant\Actions\ProgressKot;
use Modules\Restaurant\Enums\DeliveryStatus;
use Modules\Restaurant\Enums\OrderStatus;
use Modules\Restaurant\Enums\TableReservationStatus;
use Modules\Restaurant\Events\DeliveryStatusChanged;
use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosTerminal;
use Modules\Restaurant\Models\TableReservation;
use Modules\Restaurant\Services\TableReservations;

use function Pest\Laravel\get;
use function Pest\Laravel\json;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../Support/pos-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(fn () => app(DefaultChargeCodes::class)->ensure(TaxCategory::query()->where('code', 'ROOM')->value('id')));
    Notification::fake();
    Event::fake([DeliveryStatusChanged::class]);
});

/**
 * The signed-in person works in the terminal's outlet.
 */
function worksIn(PosTerminal $terminal, User $user): void
{
    DB::table('outlet_user')->insert(['tenant_id' => $user->tenant_id, 'outlet_id' => $terminal->outlet_id, 'user_id' => $user->id]);
}

it('takes a room-service order for an in-house guest, follows its delivery and charges it to the room', function (): void {
    $setup = billSetup();
    $stay = guestIn('401', 1, 1);
    cashierOn($setup);

    post(tenantUrl('sunrise', '/pos/orders'), ['type' => 'room_service'])->assertSessionHasErrors('reservation_id');
    post(tenantUrl('sunrise', '/pos/orders'), ['type' => 'room_service', 'reservation_id' => 999999])->assertSessionHas('error', 'Choose the in-house guest to deliver to.');
    post(tenantUrl('sunrise', '/pos/orders'), ['type' => 'room_service', 'reservation_id' => $stay->id])->assertRedirect();
    $order = booking(fn (): PosOrder => PosOrder::query()->sole());

    expect($order->only(['reservation_id', 'delivery_location', 'delivery_status', 'order_type']))->toMatchArray(['reservation_id' => $stay->id, 'delivery_location' => 'Room 401', 'delivery_status' => DeliveryStatus::Ordered])
        ->and($order->guest_name)->toBe(stayGuest($stay));
    get(tenantUrl('sunrise', '/pos/orders/'.$order->id))->assertOk()->assertSeeHtml('data-delivery-info');

    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan'], 'quantity' => 2])->assertOk();
    json('POST', tenantUrl('sunrise', "/pos/api/orders/{$order->id}/delivery"), ['status' => 'out_for_delivery'])->assertStatus(422)->assertJsonPath('message', 'Send the order to the kitchen first.');
    orderApi('POST', $order, '/send')->assertOk();

    // The kitchen starts it: the delivery is being prepared.
    $kot = booking(fn (): Kot => Kot::query()->sole());
    booking(fn () => ProgressKot::make()->handle($kot, 'start'));
    expect(booking(fn () => PosOrder::query()->findOrFail($order->id)->delivery_status))->toBe(DeliveryStatus::Preparing);

    json('POST', tenantUrl('sunrise', "/pos/api/orders/{$order->id}/delivery"), ['status' => 'delivered'])->assertStatus(422)->assertJsonPath('message', 'A delivery that is preparing cannot become delivered.');
    json('POST', tenantUrl('sunrise', "/pos/api/orders/{$order->id}/delivery"), ['status' => 'out_for_delivery'])->assertOk()->assertJsonPath('floor.orders.0.delivery.status', 'out_for_delivery');
    json('POST', tenantUrl('sunrise', "/pos/api/orders/{$order->id}/delivery"), ['status' => 'delivered'])->assertOk();
    Event::assertDispatchedTimes(DeliveryStatusChanged::class, 3);
    $stored = booking(fn () => PosOrder::query()->findOrFail($order->id));
    expect($stored->delivery_status)->toBe(DeliveryStatus::Delivered)->and($stored->out_for_delivery_at)->not->toBeNull()->and($stored->delivered_at)->not->toBeNull();

    // Charged to the room: the guest's folio carries the bill.
    $bill = billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->assertOk()->json('billing.bills.0');
    chargeRoom($bill['id'], $bill['grand_total'], $stay->id)->assertOk()->assertJsonPath('billing.bills.0.status', 'settled');
    expect(restaurantLine($bill['id'])->total)->toBe($bill['grand_total']);
});

it('takes a delivery to a place, and keeps deliveries on the floor list', function (): void {
    $setup = billSetup();
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');

    post(tenantUrl('sunrise', '/pos/orders'), ['type' => 'location_delivery'])->assertSessionHasErrors('location');
    post(tenantUrl('sunrise', '/pos/orders'), ['type' => 'location_delivery', 'location' => 'Pool deck, bed 4'])->assertRedirect();
    $order = booking(fn (): PosOrder => PosOrder::query()->sole());
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['mojito']])->assertOk();

    $floor = json('GET', tenantUrl('sunrise', '/pos/api/floor'))->assertOk();
    expect($floor->json('floor.orders.0'))->toMatchArray(['where' => 'Delivery · Pool deck, bed 4'])
        ->and($floor->json('floor.orders.0.delivery.status'))->toBe('ordered');
    get(tenantUrl('sunrise', '/pos/floor'))->assertOk()->assertSeeHtml('data-delivery')->assertSeeHtml('data-room-service');
});

it('lets staff meals be opened only with the permission and settles them as complimentary without a PIN', function (): void {
    $setup = billSetup();
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    post(tenantUrl('sunrise', '/pos/orders'), ['type' => 'staff_meal', 'name' => 'Kitchen team'])->assertForbidden();
    get(tenantUrl('sunrise', '/pos/floor'))->assertOk()->assertDontSeeHtml('data-staff-meal-start');

    $cashier = posStaff(DefaultRole::OutletCashier, '2222', $setup['terminal']);
    onDevice($setup['terminal']);
    pinSignIn($cashier, '2222')->assertRedirect();
    $session = openSession($setup['terminal'], $cashier->id);
    post(tenantUrl('sunrise', '/pos/orders'), ['type' => 'staff_meal'])->assertSessionHasErrors('name');
    post(tenantUrl('sunrise', '/pos/orders'), ['type' => 'staff_meal', 'name' => 'Kitchen team', 'covers' => 4])->assertRedirect();
    $order = booking(fn (): PosOrder => PosOrder::query()->sole());
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan'], 'quantity' => 4])->assertOk();
    orderApi('POST', $order, '/send')->assertOk();
    $bill = billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->assertOk()->assertJsonPath('billing.type', 'staff_meal')->json('billing.bills.0');

    // Only a staff meal can be comped this way, and only for the staff-meal reason.
    billApi("bills/{$bill['id']}/comp", ['comp_reason' => 'management_guest'])->assertStatus(422)->assertJsonPath('approval', 'bill.comp');
    billApi("bills/{$bill['id']}/comp", ['comp_reason' => 'staff_meal'])->assertOk()->assertJsonPath('billing.bills.0.complimentary', true)->assertJsonPath('billing.status', 'settled');
    expect($session->id)->toBeInt();
});

it('books tables in the property\'s time, refusing small or double-booked tables', function (): void {
    $setup = billSetup();
    worksIn($setup['terminal'], staffUser(DefaultRole::FnbManager));
    $outlet = $setup['terminal']->outlet_id;
    $t1 = $setup['tables']['T1'];
    $day = roomDay()->addDay()->toDateString();
    $form = ['outlet_id' => $outlet, 'date' => $day, 'time' => '19:00', 'party_size' => 3, 'dining_table_id' => $t1, 'customer_name' => 'Mr Karim', 'phone' => '01711555010', 'occasion' => 'Birthday'];

    get(tenantUrl('sunrise', "/restaurant/reservations?outlet={$outlet}&date={$day}"))->assertOk()->assertSeeHtml('data-reservation-form');
    post(tenantUrl('sunrise', '/restaurant/reservations'), [...$form, 'party_size' => 9])->assertSessionHas('error', 'Table T1 seats 4, for a party of 9.');
    post(tenantUrl('sunrise', '/restaurant/reservations'), [...$form, 'customer_name' => ''])->assertSessionHas('error', 'Enter the customer\'s name.');
    post(tenantUrl('sunrise', '/restaurant/reservations'), $form)->assertRedirect()->assertSessionHas('success', 'Table booked for Mr Karim.');
    post(tenantUrl('sunrise', '/restaurant/reservations'), [...$form, 'time' => '20:00', 'customer_name' => 'Late party'])->assertSessionHas('error', 'Table T1 is already booked around that time.');
    post(tenantUrl('sunrise', '/restaurant/reservations'), [...$form, 'time' => '20:30', 'customer_name' => 'Later party'])->assertSessionHas('success');

    $reservation = booking(fn (): TableReservation => TableReservation::query()->where('customer_name', 'Mr Karim')->sole());
    expect($reservation->reserved_for->copy()->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))->toBe($day.' 19:00')
        ->and($reservation->status)->toBe(TableReservationStatus::Booked);
    get(tenantUrl('sunrise', "/restaurant/reservations?outlet={$outlet}&date={$day}"))->assertOk()->assertSeeHtml('data-reservation-row="Mr Karim"')->assertSee('Birthday');

    put(tenantUrl('sunrise', "/restaurant/reservations/{$reservation->id}"), [...$form, 'party_size' => 4, 'time' => '19:00'])->assertSessionHas('success', 'Reservation saved.');
    post(tenantUrl('sunrise', "/restaurant/reservations/{$reservation->id}/close"), ['status' => 'cancelled'])->assertSessionHas('success');
    post(tenantUrl('sunrise', "/restaurant/reservations/{$reservation->id}/close"), ['status' => 'no_show'])->assertSessionHas('error', 'Only a booked reservation can be marked as a no-show.');
    put(tenantUrl('sunrise', "/restaurant/reservations/{$reservation->id}"), $form)->assertSessionHas('error', 'Only a booked reservation can be changed.');
    // A cancelled booking frees its table.
    post(tenantUrl('sunrise', '/restaurant/reservations'), [...$form, 'customer_name' => 'Replacement'])->assertSessionHas('success');
});

it('books an in-house guest, shows the table as reserved, seats the party and completes it when the bill is settled', function (): void {
    $setup = billSetup();
    $stay = guestIn('401', 1, 1);
    $manager = posStaff(DefaultRole::FnbManager, '9999', $setup['terminal']);
    onDevice($setup['terminal']);
    pinSignIn($manager, '9999')->assertRedirect();
    $session = openSession($setup['terminal'], $manager->id);
    $outlet = $setup['terminal']->outlet;
    $soon = now()->addMinutes(30);

    $reservation = booking(fn (): TableReservation => TableReservation::factory()->create(['outlet_id' => $outlet->id, 'dining_table_id' => $setup['tables']['T2'], 'reservation_id' => $stay->id,
        'customer_name' => stayGuest($stay), 'reserved_for' => $soon, 'party_size' => 3]));

    $floor = json('GET', tenantUrl('sunrise', '/pos/api/floor'))->assertOk();
    expect($floor->json('floor.tables.'.$setup['tables']['T2'].'.status'))->toBe('reserved')
        ->and($floor->json('floor.reservations.0'))->toMatchArray(['party' => 3, 'table_id' => $setup['tables']['T2']])
        ->and(booking(fn () => app(TableReservations::class)->reservedTableIds($outlet->id)))->toBe([$setup['tables']['T2']]);
    get(tenantUrl('sunrise', '/pos/floor'))->assertOk()->assertSeeHtml('data-reservations');

    post(tenantUrl('sunrise', "/pos/reservations/{$reservation->id}/seat"))->assertRedirect();
    $order = booking(fn (): PosOrder => PosOrder::query()->sole());
    expect($order->covers)->toBe(3)->and($order->dining_table_id)->toBe($setup['tables']['T2'])
        ->and(booking(fn () => TableReservation::query()->findOrFail($reservation->id))->only(['status', 'pos_order_id']))->toBe(['status' => TableReservationStatus::Seated, 'pos_order_id' => $order->id]);
    post(tenantUrl('sunrise', "/pos/reservations/{$reservation->id}/seat"))->assertSessionHas('error', 'This reservation is seated.');

    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan']])->assertOk();
    orderApi('POST', $order, '/send')->assertOk();
    $bill = billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->assertOk()->json('billing.bills.0');
    billApi("bills/{$bill['id']}/payments", ['method' => 'cash', 'amount' => $bill['grand_total'], 'tendered' => '500'])->assertOk();

    expect(booking(fn () => TableReservation::query()->findOrFail($reservation->id)->status))->toBe(TableReservationStatus::Completed)
        ->and(booking(fn () => PosOrder::query()->findOrFail($order->id)->status))->toBe(OrderStatus::Settled)
        ->and($session->id)->toBeInt();
});

it('keeps reservations and reports to those who may see them', function (): void {
    $setup = billSetup();
    worksIn($setup['terminal'], staffUser(DefaultRole::Waiter));
    get(tenantUrl('sunrise', '/restaurant/reservations'))->assertOk()->assertDontSeeHtml('data-reservation-form');
    post(tenantUrl('sunrise', '/restaurant/reservations'), ['outlet_id' => $setup['terminal']->outlet_id, 'date' => '2026-12-01', 'time' => '19:00', 'party_size' => 2, 'customer_name' => 'X'])->assertForbidden();
    get(tenantUrl('sunrise', '/restaurant/reports/sales'))->assertForbidden();
});
