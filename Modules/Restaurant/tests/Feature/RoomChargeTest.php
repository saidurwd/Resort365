<?php

/*
| Charge to room, packages & night audit integration (Step 3.7). "Done when": a bill charged to an
| in-house guest appears on their folio and on the check-out invoice; a checked-out guest cannot be
| charged; a CP guest's breakfast is redeemed at zero and counted.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\Enums\CityLedgerStatus;
use Modules\Billing\Enums\FolioType;
use Modules\Billing\Enums\PaymentMethod as BillingMethod;
use Modules\Billing\Models\CityLedgerEntry;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Services\DefaultChargeCodes;
use Modules\Core\Contracts\Settings;
use Modules\Core\Models\TaxCategory;
use Modules\FrontOffice\Actions\CheckOutGuest;
use Modules\FrontOffice\Actions\PostStayCharges;
use Modules\FrontOffice\Services\NightAuditChecks;
use Modules\Guest\Models\Company;
use Modules\Reservation\Actions\SetRoomCharges;
use Modules\Reservation\Models\Reservation;
use Modules\Restaurant\Actions\ClosePosSession;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Models\OutletMenuItem;
use Modules\Restaurant\Models\PackageRedemption;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosSession;

use function Pest\Laravel\get;
use function Pest\Laravel\json;
use function Pest\Laravel\put;

require_once __DIR__.'/../Support/pos-setup.php';

uses(RefreshDatabase::class);

const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(fn () => app(DefaultChargeCodes::class)->ensure(TaxCategory::query()->where('code', 'ROOM')->value('id')));
    Notification::fake();
});

it('charges a bill to an in-house guest\'s folio, onto their check-out invoice, and never after check-out (done when)', function (): void {
    $setup = billSetup();
    $stay = guestIn('401');
    cashierOn($setup);
    $bill = naanAndMojito($setup);

    $stays = json('GET', tenantUrl('sunrise', '/pos/api/stays?term=401'))->assertOk()->json('stays');
    expect($stays)->toHaveCount(1)->and($stays[0])->toMatchArray(['reservationId' => $stay->id, 'rooms' => ['Room 401'], 'noRoomCharges' => false]);

    chargeRoom($bill['id'], '695.75', $stay->id, ['signature' => SIGNATURE])->assertOk()->assertJsonPath('billing.bills.0.status', 'settled')
        ->assertJsonPath('billing.bills.0.payments.0.charged_to', 'Room 401 · '.stayGuest($stay).' · '.$stay->code);

    $line = restaurantLine($bill['id']);
    expect($line->only(['description', 'amount', 'tax_amount', 'total', 'revenue_posted_by_source']))->toBe([
        'description' => 'Main Restaurant bill MR-B000001', 'amount' => '550.00', 'tax_amount' => '145.75', 'total' => '695.75', 'revenue_posted_by_source' => true,
    ])->and(array_sum(array_map(floatval(...), $line->tax_lines)))->toEqualWithDelta(145.75, 0.001);

    get(tenantUrl('sunrise', "/pos/bills/{$bill['id']}/receipt"))->assertOk()->assertSeeHtml('data-charged-to')->assertSeeHtml('data-signature-image');

    // The front desk sees the bill on the folio, with its receipt.
    staffUser(DefaultRole::FrontOfficeManager);
    get(tenantUrl('sunrise', "/reservation/bookings/{$stay->id}"))->assertOk()->assertSee('Main Restaurant bill MR-B000001')->assertSee('Receipt MR-B000001');
    get(tenantUrl('sunrise', "/restaurant/bills/{$bill['id']}/receipt"))->assertOk()->assertSee('COPY');

    // Check-out: room nights posted, the folio paid, the invoice lists the restaurant bill.
    booking(fn () => PostStayCharges::make()->handle($stay->id));
    $folio = booking(fn (): Folio => Folio::query()->where('reservation_id', $stay->id)->where('type', FolioType::Guest->value)->sole());
    booking(fn () => RecordPayment::make()->handle(new NewPayment($stay->id, BillingMethod::Card, $folio->balance, folioId: $folio->id)));
    $invoices = booking(fn (): array => CheckOutGuest::make()->handle($stay->id));
    expect($invoices)->toHaveCount(1)
        ->and(booking(fn () => DB::table('invoice_lines')->where('invoice_id', $invoices[0]->id)->where('description', 'Main Restaurant bill MR-B000001')->value('total')))->toBe('695.75');

    // After check-out the guest cannot be charged.
    $bartender = posStaff(DefaultRole::Bartender, '3333', $setup['terminal']);
    onDevice($setup['terminal']);
    pinSignIn($bartender, '3333')->assertRedirect();
    $next = naanAndMojito($setup, 'T2');
    chargeRoom($next['id'], '695.75', $stay->id)->assertStatus(422)->assertJsonPath('message', 'Choose a guest who is in house.');
    expect(json('GET', tenantUrl('sunrise', '/pos/api/stays?term=401'))->json('stays'))->toBe([]);

    // Nor can the charged bill be voided any more: the folio is closed.
    $manager = posStaff(DefaultRole::FnbManager, '9999', $setup['terminal']);
    onDevice($setup['terminal']);
    pinSignIn($manager, '9999')->assertRedirect();
    billApi("bills/{$bill['id']}/void", ['reason' => 'Too late'])->assertStatus(422)
        ->assertJsonPath('message', fn (string $message): bool => str_starts_with($message, 'Bill MR-B000001 cannot be voided:') && str_contains($message, 'credit note'));
    expect(storedBill('MR-B000001')->status)->toBe(BillStatus::Settled);
});

it('refuses room charges a booking does not take or cannot afford, and shares taxes on part payments', function (): void {
    $setup = billSetup();
    $stay = guestIn('401', 1, 1);
    cashierOn($setup);
    $bill = naanAndMojito($setup);

    chargeRoom($bill['id'], '695.75', $stay->id, ['tip' => '20'])->assertStatus(422)->assertJsonPath('message', 'A tip cannot be charged to a room or an account; take it in cash or by card.');

    booking(fn () => SetRoomCharges::make()->handle(Reservation::query()->findOrFail($stay->id), true));
    expect(json('GET', tenantUrl('sunrise', '/pos/api/stays?term=Rahim'))->json('stays.0.noRoomCharges'))->toBeTrue();
    chargeRoom($bill['id'], '695.75', $stay->id)->assertStatus(422)->assertJsonPath('message', "Booking {$stay->code} takes no room charges from outlets.");
    booking(fn () => SetRoomCharges::make()->handle(Reservation::query()->findOrFail($stay->id), false));

    booking(fn () => app(Settings::class)->set('billing.guest_credit_limit', '500', bookingIds()['property']));
    chargeRoom($bill['id'], '695.75', $stay->id)->assertStatus(422)->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'credit limit of 500'));
    expect(booking(fn () => FolioLine::query()->where('reference_type', 'pos_bill')->count()))->toBe(0);

    // 200 in cash, the rest (495.75) to the room with its share of the taxes.
    billApi("bills/{$bill['id']}/payments", ['method' => 'cash', 'amount' => '200', 'tendered' => '200'])->assertOk();
    chargeRoom($bill['id'], '495.75', $stay->id)->assertOk()->assertJsonPath('billing.bills.0.status', 'settled');
    $line = restaurantLine($bill['id']);
    expect($line->total)->toBe('495.75')->and(bcadd($line->amount, $line->tax_amount, 2))->toBe('495.75')->and($line->tax_amount)->toBe('103.85');
});

it('voids a bill by taking its room charge back off the folio', function (): void {
    $setup = billSetup();
    $stay = guestIn('401', 1, 1);
    onTerminal($setup['terminal'], DefaultRole::FnbManager, '9999');
    openSession($setup['terminal'], (int) auth()->id());
    $bill = naanAndMojito($setup);
    chargeRoom($bill['id'], '695.75', $stay->id)->assertOk();

    billApi("bills/{$bill['id']}/void", ['reason' => 'Wrong room'])->assertOk()->assertJsonPath('billing.bills.0.status', 'voided');
    expect(restaurantLine($bill['id'])->is_voided)->toBeTrue()
        ->and(booking(fn () => Folio::query()->where('reservation_id', $stay->id)->where('type', FolioType::Guest->value)->sole()->balance))->toBe('0.00');
});

it('bills a company\'s account within its credit limit, and takes it back on a void', function (): void {
    $setup = billSetup();
    $company = booking(fn (): Company => Company::factory()->create(['name' => 'Dhaka Bank', 'credit_limit' => '1000.00', 'payment_terms_days' => 30, 'is_active' => true]));
    onTerminal($setup['terminal'], DefaultRole::FnbManager, '9999');
    openSession($setup['terminal'], (int) auth()->id());
    $bill = naanAndMojito($setup);

    expect(json('GET', tenantUrl('sunrise', '/pos/api/companies?term=Dhaka'))->json('companies.0'))->toMatchArray(['companyId' => $company->id, 'owed' => '0.00', 'creditLeft' => '1000.00']);
    billApi("bills/{$bill['id']}/payments", ['method' => 'city_ledger', 'amount' => '695.75'])->assertStatus(422)->assertJsonValidationErrors('company_id');
    billApi("bills/{$bill['id']}/payments", ['method' => 'city_ledger', 'amount' => '695.75', 'company_id' => $company->id])->assertOk()
        ->assertJsonPath('billing.bills.0.payments.0.charged_to', 'Dhaka Bank');
    $entry = booking(fn (): CityLedgerEntry => CityLedgerEntry::query()->where('reference_type', 'pos_bill')->sole());
    expect($entry->only(['amount', 'description']))->toBe(['amount' => '695.75', 'description' => 'Main Restaurant bill MR-B000001'])
        ->and($entry->due_on->toDateString())->toBe(roomDay()->addDays(30)->toDateString());

    $second = naanAndMojito($setup, 'T2');
    billApi("bills/{$second['id']}/payments", ['method' => 'city_ledger', 'amount' => '695.75', 'company_id' => $company->id])->assertStatus(422)
        ->assertJsonPath('message', 'Dhaka Bank would go over its credit limit of 1000.00.');

    billApi("bills/{$bill['id']}/void", ['reason' => 'Billed to the wrong company'])->assertOk();
    expect(booking(fn () => CityLedgerEntry::query()->findOrFail($entry->id)->status))->toBe(CityLedgerStatus::Cancelled);
});

it('redeems a CP guest\'s breakfast at nothing and counts it (done when)', function (): void {
    $setup = billSetup();
    booking(fn () => DB::table('rate_plans')->where('code', 'RO')->update(['meal_plan' => 'CP']));
    $stay = guestIn('401', 1, 1);
    booking(fn () => OutletMenuItem::query()->where('menu_item_id', $setup['items']['naan'])->update(['is_package_eligible' => true]));
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    $manager = posStaff(DefaultRole::FnbManager, '9999', $setup['terminal']);

    $order = openTable($setup['tables']['T1']);
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan'], 'quantity' => 2])->assertOk();
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['mojito']])->assertOk();
    orderApi('POST', $order, '/send')->assertOk();

    $left = json('GET', tenantUrl('sunrise', "/pos/api/orders/{$order->id}/meal-plan?reservation={$stay->id}&period=breakfast"))->assertOk();
    expect($left->json('left'))->toBe(['entitled' => 2, 'taken' => 0, 'left' => 2, 'plans' => ['CP']]);

    $redeemed = billApi("orders/{$order->id}/meal-plan", ['reservation_id' => $stay->id, 'period' => 'breakfast', 'adults' => 2])->assertOk();
    expect($redeemed->json('billing.redemption'))->toMatchArray(['code' => $stay->code, 'period' => 'Breakfast', 'covers' => 2, 'items' => 1])
        ->and($redeemed->json('billing.preview'))->toMatchArray(['subtotal' => '350.00', 'grand_total' => '442.75'])
        ->and($redeemed->json('billing.preview.lines.0.name'))->toBe('Naan · meal plan');

    $bill = billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->assertOk()->json('billing.bills.0');
    $redemption = booking(fn (): PackageRedemption => PackageRedemption::query()->sole());
    expect($redemption->only(['reservation_id', 'covers_adults', 'entitled', 'pos_bill_id']))->toBe(['reservation_id' => $stay->id, 'covers_adults' => 2, 'entitled' => 2, 'pos_bill_id' => $bill['id']])
        ->and($redemption->meal_period->value)->toBe('breakfast');

    // A third breakfast is over the plan: a manager approves it. Dinner is not on a CP plan.
    $more = openTable($setup['tables']['T2']);
    orderApi('POST', $more, '/lines', ['item_id' => $setup['items']['naan']])->assertOk();
    orderApi('POST', $more, '/send')->assertOk();
    billApi("orders/{$more->id}/meal-plan", ['reservation_id' => $stay->id, 'period' => 'breakfast', 'adults' => 1])->assertStatus(422)
        ->assertJsonPath('approval', 'package.over')->assertJsonPath('message', stayGuest($stay).' has 0 of 2 breakfast covers left today. More needs a manager\'s approval.');
    billApi("orders/{$more->id}/meal-plan", ['reservation_id' => $stay->id, 'period' => 'dinner', 'adults' => 1])->assertStatus(422)
        ->assertJsonPath('message', stayGuest($stay).'\'s plan does not include dinner today. More needs a manager\'s approval.');
    $approval = billApi('approvals', ['action' => 'package.over', 'manager_id' => $manager->id, 'pin' => '9999', 'subject_id' => $more->id])->json('approval_id');
    billApi("orders/{$more->id}/meal-plan", ['reservation_id' => $stay->id, 'period' => 'breakfast', 'adults' => 1, 'approval_id' => $approval])->assertOk();

    // All of it on the plan: the bill comes to nothing and is settled at once.
    billApi("orders/{$more->id}/bill/print", ['mode' => 'none'])->assertOk()->assertJsonPath('billing.bills.0.grand_total', '0.00')->assertJsonPath('billing.bills.0.status', 'settled');
    expect(booking(fn () => PosOrder::query()->findOrFail($more->id)->status->value))->toBe('settled')
        ->and(booking(fn () => PackageRedemption::query()->sum('covers_adults')))->toEqual(3);
});

it('refuses a meal plan for a room-only guest or an order without plan items', function (): void {
    $setup = billSetup();
    booking(fn () => DB::table('rate_plans')->where('code', 'RO')->update(['meal_plan' => 'EP']));
    $stay = guestIn('402', 1, 1);
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    $order = openTable($setup['tables']['T1']);
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['mojito']])->assertOk();

    billApi("orders/{$order->id}/meal-plan", ['reservation_id' => $stay->id, 'period' => 'breakfast', 'adults' => 1])->assertStatus(422)
        ->assertJsonPath('message', 'Nothing on this order is on the meal-plan menu.');
    booking(fn () => OutletMenuItem::query()->where('menu_item_id', $setup['items']['mojito'])->update(['is_package_eligible' => true]));
    billApi("orders/{$order->id}/meal-plan", ['reservation_id' => $stay->id, 'period' => 'breakfast', 'adults' => 1])->assertStatus(422)
        ->assertJsonPath('message', stayGuest($stay).'\'s plan does not include breakfast today. More needs a manager\'s approval.');
    billApi("orders/{$order->id}/meal-plan", ['reservation_id' => 999999, 'period' => 'breakfast', 'adults' => 1])->assertStatus(422)->assertJsonPath('message', 'Choose a guest who is in house.');
    expect(booking(fn () => PackageRedemption::query()->count()))->toBe(0);
});

it('keeps the night audit waiting while a POS session is open', function (): void {
    $setup = billSetup();
    $session = cashierOn($setup);
    $property = bookingIds()['property'];

    expect(booking(fn () => app(NightAuditChecks::class)->preview($property)['blocking']))->toBe(['The POS session on Cashier desk (Main Restaurant) is still open: close it on the POS.']);
    booking(fn () => ClosePosSession::make()->handle(PosSession::query()->findOrFail($session->id), ['1000' => 2], null, (int) $session->opened_by));
    expect(booking(fn () => app(NightAuditChecks::class)->preview($property)['blocking']))->toBe([]);
});

it('lets the front desk block and allow room charges on a booking', function (): void {
    $stay = guestIn('401', 1, 1);
    staffUser(DefaultRole::FrontOfficeManager);

    get(tenantUrl('sunrise', "/reservation/bookings/{$stay->id}"))->assertOk()->assertSeeHtml('data-room-charges="allowed"');
    put(tenantUrl('sunrise', "/reservation/bookings/{$stay->id}/room-charges"), ['no_room_charges' => 1])->assertSessionHas('success', 'Outlets may no longer charge this booking.');
    expect(freshReservation($stay->id)->no_room_charges)->toBeTrue();
    get(tenantUrl('sunrise', "/reservation/bookings/{$stay->id}"))->assertSeeHtml('data-room-charges="blocked"')->assertSee('Room charges');
});
