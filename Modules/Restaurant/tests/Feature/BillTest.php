<?php

/*
| Bills, discounts & payments (Step 3.6). "Done when": a 4-person table split equally is settled by
| cash + card (the split always adds up: see BillSplitterTest).
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Modules\Restaurant\Actions\OpenPosSession;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\OrderStatus;
use Modules\Restaurant\Enums\TableStatus;
use Modules\Restaurant\Events\RestaurantBillSettled;
use Modules\Restaurant\Events\RestaurantBillVoided;
use Modules\Restaurant\Events\TableStatusChanged;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\DiscountLimit;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosPayment;
use Modules\Restaurant\Models\PosSession;
use Modules\Restaurant\Models\PosTerminal;
use Modules\Restaurant\Services\SessionCash;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\json;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../Support/pos-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
    Event::fake([RestaurantBillSettled::class, RestaurantBillVoided::class, TableStatusChanged::class]);
});

/**
 * The order setup, with the outlet taxed SC 10% then VAT 15% (the ROOM category of the booking setup).
 *
 * @return array<string, mixed>
 */
function billSetup(bool $inclusive = false): array
{
    $setup = orderSetup();
    booking(fn () => DB::table('outlets')->where('id', $setup['terminal']->outlet_id)->update([
        'default_tax_category_id' => DB::table('tax_categories')->where('tenant_id', tenant('sunrise')->id)->where('code', 'ROOM')->value('id'), 'prices_include_tax' => $inclusive,
    ]));

    return $setup;
}

/**
 * A sent order at T1: 2 × curry (Full), 4 × naan, 4 × mojito = 3,100.00 before tax.
 *
 * @param  array<string, mixed>  $setup
 */
function fourCovers(array $setup): PosOrder
{
    $order = openTable($setup['tables']['T1'], 4);
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['curry'], 'variant_id' => $setup['variants']['Full'], 'modifier_ids' => [$setup['modifiers']['mild']], 'quantity' => 2])->assertOk();
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan'], 'quantity' => 4, 'seat' => 1])->assertOk();
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['mojito'], 'quantity' => 4, 'seat' => 2])->assertOk();
    orderApi('POST', $order, '/send')->assertOk();

    return $order;
}

function openSession(PosTerminal $terminal, int $userId): PosSession
{
    return booking(fn (): PosSession => OpenPosSession::make()->handle($terminal, $userId, '2000'));
}

/**
 * @param  array<string, mixed>  $data
 * @return TestResponse<Response>
 */
function billApi(string $path, array $data = []): TestResponse
{
    return json('POST', tenantUrl('sunrise', '/pos/api/'.$path), $data);
}

function storedBill(string $billNo): PosBill
{
    return booking(fn (): PosBill => PosBill::query()->where('bill_no', $billNo)->sole());
}

it('splits a 4-person table equally and settles it by cash and card (done when)', function (): void {
    $setup = billSetup();
    $cashier = posStaff(DefaultRole::OutletCashier, '2222', $setup['terminal']);
    onDevice($setup['terminal']);
    pinSignIn($cashier, '2222')->assertRedirect();
    $session = openSession($setup['terminal'], $cashier->id);
    $order = fourCovers($setup);

    get(tenantUrl('sunrise', '/pos/orders/'.$order->id.'/bill'))->assertOk()->assertSeeHtml('data-bill-screen="'.$order->id.'"');

    // One bill: 3,100.00 + service charge 310.00 + VAT 511.50 (15% of 3,410.00) = 3,921.50.
    $one = billApi("orders/{$order->id}/bill/preview", ['mode' => 'none'])->assertOk();
    expect($one->json('bills.0'))->toMatchArray(['subtotal' => '3100.00', 'service_charge' => '310.00', 'tax_total' => '511.50', 'grand_total' => '3921.50']);

    $printed = billApi("orders/{$order->id}/bill/print", ['mode' => 'equal', 'bills' => 4])->assertOk();
    $bills = $printed->json('billing.bills');
    expect(array_column($bills, 'bill_no'))->toBe(['MR-B000001', 'MR-B000002', 'MR-B000003', 'MR-B000004'])
        ->and(array_column($bills, 'grand_total'))->toBe(['980.38', '980.38', '980.37', '980.37'])
        ->and(array_column($bills, 'label'))->toBe(['1 of 4', '2 of 4', '3 of 4', '4 of 4'])
        ->and(array_sum(array_map(floatval(...), array_column($bills, 'grand_total'))))->toEqualWithDelta(3921.50, 0.001)
        ->and($printed->json('print'))->toHaveCount(4)
        ->and(booking(fn () => DiningTable::query()->findOrFail($setup['tables']['T1'])->status))->toBe(TableStatus::BillPrinted);

    // Items cannot be added any more.
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan']])->assertStatus(422);
    get(tenantUrl('sunrise', '/pos/orders/'.$order->id))->assertRedirect(tenantUrl('sunrise', '/pos/orders/'.$order->id.'/bill'));

    [$first, $second, $third, $fourth] = array_column($bills, 'id');
    billApi("bills/{$first}/payments", ['method' => 'cash', 'amount' => '980.38', 'tendered' => '1000'])->assertOk();
    billApi("bills/{$second}/payments", ['method' => 'card', 'amount' => '980.38', 'reference' => '4417'])->assertOk();
    billApi("bills/{$third}/payments", ['method' => 'cash', 'amount' => '500', 'tendered' => '500'])->assertOk()->assertJsonPath('billing.bills.2.due', '480.37');
    billApi("bills/{$third}/payments", ['method' => 'card', 'amount' => '480.37', 'reference' => '9921'])->assertOk()->assertJsonPath('billing.bills.2.status', 'settled');
    billApi("bills/{$fourth}/payments", ['method' => 'card', 'amount' => '980.37', 'tip' => '50', 'reference' => '1180'])->assertOk()
        ->assertJsonPath('billing.status', 'settled');

    expect(booking(fn () => PosPayment::query()->where('pos_bill_id', $first)->sole()->only(['tendered', 'change_given'])))->toBe(['tendered' => '1000.00', 'change_given' => '19.62'])
        ->and(booking(fn () => PosOrder::query()->findOrFail($order->id))->status)->toBe(OrderStatus::Settled)
        ->and(booking(fn () => DiningTable::query()->findOrFail($setup['tables']['T1'])->status))->toBe(TableStatus::Available)
        ->and(booking(fn () => app(SessionCash::class)->cash($session)))->toBe(['1480.38', '0.00'])
        ->and(booking(fn () => app(SessionCash::class)->byMethod($session)))->toBe(['cash' => '1480.38', 'card' => '2491.12']);
    Event::assertDispatchedTimes(RestaurantBillSettled::class, 4);
    Event::assertDispatched(RestaurantBillSettled::class, fn (RestaurantBillSettled $event): bool => $event->billId === $third && $event->payments === ['cash' => '500.00', 'card' => '480.37']);

    // Receipts: the first is the original, then COPY.
    get(tenantUrl('sunrise', "/pos/bills/{$first}/receipt"))->assertOk()->assertSeeHtml('data-bill-document="receipt" data-copy="original"')
        ->assertSee('MR-B000001')->assertSee('10%')->assertSee('15%')->assertSee('0.5 × Chicken curry (Full)')->assertSee('Change 19.62');
    get(tenantUrl('sunrise', "/pos/bills/{$first}/receipt"))->assertOk()->assertSee('COPY');
    get(tenantUrl('sunrise', '/pos/sessions/'.$session->id.'/report'))->assertOk()->assertSeeHtml('data-by-method')->assertSee('2,491.12');
});

it('keeps discounts within the role\'s limit unless a manager approves', function (): void {
    $setup = billSetup();
    $manager = posStaff(DefaultRole::FnbManager, '9999', $setup['terminal']);
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    booking(fn () => DiscountLimit::factory()->create(['role_id' => roleId(tenant('sunrise'), DefaultRole::Waiter), 'max_percent' => '5']));
    $order = fourCovers($setup);
    $naan = booking(fn (): int => (int) DB::table('pos_order_lines')->where('pos_order_id', $order->id)->where('name_snapshot', 'Naan')->value('id'));

    billApi("orders/{$order->id}/discount", ['line_id' => $naan, 'type' => 'percent', 'value' => '5'])->assertStatus(422)->assertJsonValidationErrors('reason');
    billApi("orders/{$order->id}/discount", ['line_id' => $naan, 'type' => 'amount', 'value' => '20', 'reason' => 'Burnt edges'])->assertOk()
        ->assertJsonPath('billing.lines.1.discount', ['type' => 'amount', 'value' => '20.00', 'reason' => 'Burnt edges']);

    // 10% of the bill is above the waiter's 5%: a manager approves it.
    billApi("orders/{$order->id}/discount", ['type' => 'percent', 'value' => '10', 'reason' => 'Regular guest'])->assertStatus(422)
        ->assertJsonPath('approval', 'bill.discount')->assertJsonPath('message', 'A 10.00% discount is above your limit of 5.00%: a manager must approve it.');
    $approval = billApi('approvals', ['action' => 'bill.discount', 'manager_id' => $manager->id, 'pin' => '9999', 'subject_id' => $order->id])->assertOk()->json('approval_id');
    billApi("orders/{$order->id}/discount", ['type' => 'percent', 'value' => '10', 'reason' => 'Regular guest', 'approval_id' => $approval])->assertOk();

    // 3,100 − 20 = 3,080; −10% = 308 → 2,772; SC 277.20; VAT 457.38 → 3,506.58.
    $preview = billApi("orders/{$order->id}/bill/preview", ['mode' => 'none'])->assertOk();
    expect($preview->json('bills.0'))->toMatchArray(['subtotal' => '3100.00', 'discount_total' => '328.00', 'service_charge' => '277.20', 'grand_total' => '3506.58'])
        ->and(booking(fn () => PosOrder::query()->findOrFail($order->id)->discount_approval_id))->toBe($approval);

    billApi("orders/{$order->id}/discount", [])->assertOk()->assertJsonPath('billing.discount', null);
});

it('reopens a printed bill only before payment, with a manager\'s approval for staff', function (): void {
    $setup = billSetup();
    $manager = posStaff(DefaultRole::FnbManager, '9999', $setup['terminal']);
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    $order = fourCovers($setup);
    billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->assertOk();

    billApi("orders/{$order->id}/reopen")->assertStatus(422)->assertJsonPath('approval', 'bill.reopen');
    $approval = billApi('approvals', ['action' => 'bill.reopen', 'manager_id' => $manager->id, 'pin' => '9999', 'subject_id' => $order->id])->json('approval_id');
    billApi("orders/{$order->id}/reopen", ['approval_id' => $approval])->assertOk()->assertJsonPath('billing.status', 'open')->assertJsonPath('billing.bills', []);

    expect(storedBill('MR-B000001')->status)->toBe(BillStatus::Voided);
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['lemonade']])->assertOk();
    orderApi('POST', $order, '/send')->assertOk();
    billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->assertOk()->assertJsonPath('billing.bills.0.bill_no', 'MR-B000002');
});

it('makes a bill complimentary and voids a settled bill with a manager\'s PIN, refunding it', function (): void {
    $setup = billSetup();
    $manager = posStaff(DefaultRole::FnbManager, '9999', $setup['terminal']);
    $cashier = posStaff(DefaultRole::OutletCashier, '2222', $setup['terminal']);
    onDevice($setup['terminal']);
    pinSignIn($cashier, '2222')->assertRedirect();
    $session = openSession($setup['terminal'], $cashier->id);
    $order = fourCovers($setup);
    [$comp, $paid] = array_column(billApi("orders/{$order->id}/bill/print", ['mode' => 'seat'])->assertOk()->json('billing.bills'), 'id');

    // Complimentary: reason, then the manager's PIN.
    billApi("bills/{$comp}/comp", ['comp_reason' => 'service_recovery'])->assertStatus(422)->assertJsonPath('approval', 'bill.comp');
    billApi("bills/{$comp}/comp", ['comp_reason' => 'other'])->assertStatus(422)->assertJsonPath('message', 'Say why the bill is complimentary.');
    $approval = billApi('approvals', ['action' => 'bill.comp', 'manager_id' => $manager->id, 'pin' => '9999', 'subject_id' => $comp])->json('approval_id');
    billApi("bills/{$comp}/comp", ['comp_reason' => 'service_recovery', 'approval_id' => $approval])->assertOk()->assertJsonPath('billing.bills.0.complimentary', true);

    // Void the paid bill: refunded in the session, order voided only when nothing settled is left.
    $due = billApi("bills/{$paid}/payments", ['method' => 'cash', 'amount' => '1', 'tendered' => '1'])->json('billing.bills.1.due');
    billApi("bills/{$paid}/payments", ['method' => 'cash', 'amount' => $due])->assertOk();
    billApi("bills/{$paid}/void", ['reason' => 'Wrong table'])->assertStatus(422)->assertJsonPath('approval', 'bill.void');
    $approval = billApi('approvals', ['action' => 'bill.void', 'manager_id' => $manager->id, 'pin' => '9999', 'subject_id' => $paid])->json('approval_id');
    billApi("bills/{$paid}/void", ['reason' => 'Wrong table', 'food_prepared' => false, 'approval_id' => $approval])->assertOk()->assertJsonPath('billing.bills.1.status', 'voided');

    [$received, $refunded] = booking(fn (): array => app(SessionCash::class)->cash($session));
    expect($received)->toBe($refunded)
        ->and(booking(fn () => PosPayment::query()->where('pos_bill_id', $paid)->whereNotNull('refund_of_id')->count()))->toBe(2)
        ->and(booking(fn () => PosOrder::query()->findOrFail($order->id))->status)->toBe(OrderStatus::Settled);
    Event::assertDispatched(RestaurantBillVoided::class, fn (RestaurantBillVoided $event): bool => $event->billId === $paid && ! $event->foodPrepared);
    get(tenantUrl('sunrise', "/pos/bills/{$paid}/receipt"))->assertOk()->assertSee('VOIDED')->assertSee('refund');
});

it('voids settled bills only on their business date', function (): void {
    $setup = billSetup();
    onTerminal($setup['terminal'], DefaultRole::FnbManager, '9999');
    $session = openSession($setup['terminal'], auth()->id());
    $order = fourCovers($setup);
    $bill = billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->json('billing.bills.0');
    billApi("bills/{$bill['id']}/payments", ['method' => 'card', 'amount' => $bill['grand_total'], 'reference' => '1'])->assertOk();

    booking(fn () => DB::table('properties')->where('id', bookingIds()['property'])->update(['business_date' => now()->addDay()->toDateString()]));
    billApi("bills/{$bill['id']}/void", ['reason' => 'Late'])->assertStatus(422)->assertJsonPath('message', 'Bill MR-B000001 belongs to an earlier business date: give a refund instead.');
    expect($session->id)->toBeInt();
});

it('refuses payments that do not fit', function (): void {
    $setup = billSetup();
    $cashier = posStaff(DefaultRole::OutletCashier, '2222', $setup['terminal']);
    onDevice($setup['terminal']);
    pinSignIn($cashier, '2222')->assertRedirect();
    $order = fourCovers($setup);
    $bill = billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->json('billing.bills.0');
    $id = $bill['id'];

    billApi("bills/{$id}/payments", ['method' => 'cash', 'amount' => '10'])->assertStatus(422)->assertJsonPath('message', 'Open a cash session on this terminal to take payments.');
    $session = openSession($setup['terminal'], $cashier->id);
    billApi("bills/{$id}/payments", ['method' => 'cash', 'amount' => '5000'])->assertStatus(422)->assertJsonPath('message', 'Only 3921.50 is still due on this bill; enter anything more as a tip.');
    billApi("bills/{$id}/payments", ['method' => 'cash', 'amount' => '100', 'tendered' => '50'])->assertStatus(422);
    billApi("bills/{$id}/payments", ['method' => 'card', 'amount' => '100'])->assertStatus(422)->assertJsonPath('message', 'Enter the card reference (approval code or last digits).');
    billApi("bills/{$id}/payments", ['method' => 'room_charge', 'amount' => '100'])->assertStatus(422)->assertJsonPath('message', 'Charge to room is not taken here yet.');
    billApi("bills/{$id}/payments", ['method' => 'cash', 'amount' => '0'])->assertStatus(422)->assertJsonValidationErrors('amount');

    // A partly paid bill keeps the session open.
    billApi("bills/{$id}/payments", ['method' => 'cash', 'amount' => '100', 'tendered' => '100'])->assertOk();
    expect(booking(fn () => app(SessionCash::class)->openBills($session)))->toBe(1);
    post(tenantUrl('sunrise', '/pos/session/close'), ['count' => ['1000' => 2, '100' => 1]])->assertSessionHas('error', 'Settle or move the 1 open bill first.');
});

it('refuses splits and prints that do not fit the order', function (): void {
    $setup = billSetup();
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    $order = openTable($setup['tables']['T2']);
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan'], 'quantity' => 2])->assertOk();

    billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->assertStatus(422)->assertJsonPath('message', 'Send or remove the items not yet sent to the kitchen first.');
    orderApi('POST', $order, '/send')->assertOk();
    billApi("orders/{$order->id}/bill/preview", ['mode' => 'seat'])->assertStatus(422)->assertJsonPath('message', 'Give the items seats first: a split by seat needs at least two seats.');
    billApi("orders/{$order->id}/bill/preview", ['mode' => 'amount', 'amounts' => ['100', '100']])->assertStatus(422)
        ->assertJsonPath('message', 'The amounts add up to 200.00; the order comes to 253.00.');
    billApi("orders/{$order->id}/bill/preview", ['mode' => 'amount', 'amounts' => ['100', '153']])->assertOk()->assertJsonPath('bills.1.grand_total', '153.00');
    $line = booking(fn (): int => (int) DB::table('pos_order_lines')->where('pos_order_id', $order->id)->value('id'));
    billApi("orders/{$order->id}/bill/preview", ['mode' => 'item', 'bills' => 2, 'assignments' => [$line => [0 => 2]]])->assertStatus(422)->assertJsonPath('message', 'Bill 2 has nothing on it.');
    billApi("orders/{$order->id}/bill/preview", ['mode' => 'item', 'bills' => 2, 'assignments' => [$line => [0 => 1, 1 => 1]]])->assertOk()
        ->assertJsonPath('bills.0.grand_total', '126.50')->assertJsonPath('bills.1.grand_total', '126.50');

    // Waiters print bills but do not take payments.
    $bill = billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->assertOk()->json('billing.bills.0.id');
    billApi("bills/{$bill}/payments", ['method' => 'cash', 'amount' => '1'])->assertForbidden();
});

it('prices bills including tax when the outlet does', function (): void {
    $setup = billSetup(inclusive: true);
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    $order = openTable($setup['tables']['T2']);
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan']])->assertOk();
    orderApi('POST', $order, '/send')->assertOk();

    // 100.00 includes SC and VAT: net 79.05, SC 7.91, VAT 13.04 (rounded per tax).
    $bill = billApi("orders/{$order->id}/bill/preview", ['mode' => 'none'])->assertOk()->json('bills.0');
    expect($bill['grand_total'])->toBe('100.00')->and($bill['subtotal'])->toBe('100.00')
        ->and((float) $bill['service_charge'] + (float) $bill['tax_total'])->toBeGreaterThan(20.0);
});

it('lets managers set the discount limit of each role', function (): void {
    staffUser(DefaultRole::FnbManager);
    $waiter = roleId(tenant('sunrise'), DefaultRole::Waiter);
    $cashier = roleId(tenant('sunrise'), DefaultRole::OutletCashier);

    get(tenantUrl('sunrise', '/restaurant/discount-limits'))->assertOk()->assertSeeHtml('data-role="Waiter / Captain"');
    put(tenantUrl('sunrise', '/restaurant/discount-limits'), ['roles' => [$waiter, $cashier], 'limits' => [$waiter => '5', $cashier => '']])->assertSessionHas('success');
    put(tenantUrl('sunrise', '/restaurant/discount-limits'), ['roles' => [$waiter], 'limits' => [$waiter => '150']])->assertSessionHasErrors('limits.'.$waiter);
    expect(booking(fn () => DiscountLimit::query()->pluck('max_percent', 'role_id')->all()))->toBe([$waiter => '5.00']);

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::Waiter));
    get(tenantUrl('sunrise', '/restaurant/discount-limits'))->assertForbidden();
});
